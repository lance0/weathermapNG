#!/usr/bin/env php
<?php
// Disposable-stack fixture; state travels through protected host temp files across recreation.
require '/opt/librenms/vendor/autoload.php';
$app = require '/opt/librenms/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e): void {
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n");
    exit(1);
});
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Cookie\CookieValuePrefix;
use App\Models\User;

$mode = $argv[1];
$state = json_decode(stream_get_contents(STDIN), true) ?: [];
if ($mode === 'snapshot') {
    $result = [];
    foreach (['wmng_maps','wmng_nodes','wmng_links','wmng_map_versions','wmng_map_templates'] as $table) {
        $result[$table] = DB::table($table)->orderBy('id')->get()->all();
    }
    $result['plugins'] = DB::table('plugins')->where('plugin_name','WeathermapNG')->orderBy('plugin_id')->get()->all();
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}
function lifecycleSession(array $state): array
{
    $user = User::findOrFail($state['user']);
    $session = app('session')->driver();
    $session->start();
    auth()->guard('web')->login($user);
    $session->save();
    $name = config('session.cookie');
    $enc = app('encrypter');
    $state['cookie'] = $name.'='.$enc->encrypt(CookieValuePrefix::create($name,$enc->getKey()).$session->getId(),false);
    return $state;
}
if ($mode === 'seed') {
    $state = DB::transaction(function () use ($state) {
        $tag = 'lifecycle-'.bin2hex(random_bytes(5));
        $plugin = DB::table('plugins')->where('plugin_name','WeathermapNG')->where('version',2)->first();
        if (!$plugin) { throw new RuntimeException('Installed v2 plugin absent'); }
        $state['plugin'] = (array)$plugin;
        $settings = json_decode($plugin->settings ?: '{}', true);
        $settings['default_width'] = 1173;
        DB::table('plugins')->where('plugin_id',$plugin->plugin_id)->update(['settings'=>json_encode($settings)]);
        $state['map'] = DB::table('wmng_maps')->insertGetId(['name'=>$tag,'title'=>'Retained lifecycle map','width'=>1173,'height'=>641,'options'=>'{"retained":true}']);
        foreach (['a','b'] as $i=>$label) {
            $state['nodes'][] = DB::table('wmng_nodes')->insertGetId(['map_id'=>$state['map'],'label'=>$label,'x'=>100+$i*100,'y'=>150]);
        }
        DB::table('wmng_links')->insert(['map_id'=>$state['map'],'src_node_id'=>$state['nodes'][0],'dst_node_id'=>$state['nodes'][1],'bandwidth_bps'=>123456]);
        $state['template'] = DB::table('wmng_map_templates')->insertGetId(['name'=>$tag,'title'=>'Retained custom template','width'=>1173,'height'=>641,'config'=>'{"default_nodes":[{"label":"Template router","x":100,"y":150}],"default_links":[]}','is_built_in'=>false]);
        config(['weathermapng.webhooks.urls'=>[]]);
        app(LibreNMS\Plugins\WeathermapNG\Services\MapVersionService::class)->createVersion(
            LibreNMS\Plugins\WeathermapNG\Models\Map::findOrFail($state['map']), 'Retained version', 'Lifecycle qualification', null
        );
        $user = new User(['username'=>$tag,'auth_type'=>'mysql','enabled'=>true]);
        $user->setPassword(bin2hex(random_bytes(16)));
        $user->save();
        $user->assignRole('admin');
        $state['user'] = $user->user_id;
        return lifecycleSession($state);
    });
    echo json_encode($state, JSON_THROW_ON_ERROR);
    exit;
}
if ($mode === 'session') {
    echo json_encode(lifecycleSession($state), JSON_THROW_ON_ERROR);
    exit;
}
if ($mode === 'cleanup') {
    DB::table('wmng_links')->where('map_id',$state['map'])->delete();
    DB::table('wmng_nodes')->where('map_id',$state['map'])->delete();
    DB::table('wmng_map_versions')->where('map_id',$state['map'])->delete();
    DB::table('wmng_maps')->where('id',$state['map'])->delete();
    DB::table('wmng_map_templates')->where('id',$state['template'])->delete();
    DB::table('plugins')->where('plugin_id',$state['plugin']['plugin_id'])->update($state['plugin']);
    User::find($state['user'])?->delete();
    exit;
}
if ($mode === 'check' || $mode === 'observe') {
    if (!hash_equals(getenv('WMNG_TEST_TOKEN'), (string) config('weathermapng.security.embed_token'))) { throw new RuntimeException('CLI persistent token mismatch'); }
    if (config('weathermapng.default_width') !== 1173) { throw new RuntimeException('Effective persisted setting mismatch'); }
    $request = Request::create('http://localhost/plugin/WeathermapNG/editor/'.$state['map']);
    [$name,$value] = explode('=',$state['cookie'],2);
    $request->cookies->set($name,$value);
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request,$response);
    echo 'Session editor status='.$response->getStatusCode().' Location='.($response->headers->get('Location') ?? '(none)')."\n";
    if ($mode === 'observe') { exit; }
    if ($response->getStatusCode() !== 200) { throw new RuntimeException('Session editor rejected'); }
    if (!str_contains($response->getContent(), '__WMNGState.mapId = '.$state['map'].';')
        || !str_contains($response->getContent(), 'Retained lifecycle map')) {
        throw new RuntimeException('Session editor map identity missing');
    }
    echo "PASS: persistent CLI token and same-user session editor\n";
    exit;
}
throw new RuntimeException('Unknown fixture mode');
