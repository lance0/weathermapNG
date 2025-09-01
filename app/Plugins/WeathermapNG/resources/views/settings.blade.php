<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">{{ $title }}</h3>
    </div>
    <div class="panel-body">
        <form method="POST" action="{{ route('plugin.update', ['plugin' => $plugin_name ?? 'WeathermapNG']) }}" class="form-horizontal">
            @csrf
            <input type="hidden" name="plugin_active" value="1">
            <div class="form-group">
                <label class="col-sm-3 control-label">Poll Interval (seconds)</label>
                <div class="col-sm-9">
                    <input type="number" name="settings[poll_interval]" class="form-control" 
                           value="{{ $settings['poll_interval'] ?? 300 }}" min="60" max="3600">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3 control-label">Default Map Size</label>
                <div class="col-sm-4">
                    <input type="number" name="settings[default_width]" class="form-control" 
                           value="{{ $settings['default_width'] ?? 800 }}" min="400" max="2000">
                    <span class="help-block">Width</span>
                </div>
                <div class="col-sm-4">
                    <input type="number" name="settings[default_height]" class="form-control" 
                           value="{{ $settings['default_height'] ?? 600 }}" min="300" max="1500">
                    <span class="help-block">Height</span>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3 control-label">RRD Base Path</label>
                <div class="col-sm-9">
                    <input type="text" name="settings[rrd_base]" class="form-control" 
                           value="{{ $settings['rrd_base'] ?? '/opt/librenms/rrd' }}">
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-9">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="settings[enable_api_fallback]" value="1" 
                                   @if(($settings['enable_api_fallback'] ?? true)) checked @endif>
                            Enable API fallback when RRD files are not available
                        </label>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="settings[allow_embed]" value="1" 
                                   @if(($settings['allow_embed'] ?? true)) checked @endif>
                            Allow embedding maps
                        </label>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="settings[debug]" value="1" 
                                   @if(($settings['debug'] ?? false)) checked @endif>
                            Enable debug logging
                        </label>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-9">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Save Settings
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
