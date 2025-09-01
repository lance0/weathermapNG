<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ $title }}</h3>
    </div>
    <div class="card-body">
        @if($saved)
            <div class="alert alert-success">
                <i class="fa fa-check"></i> Settings saved successfully!
            </div>
        @endif
        
        <form method="POST" action="{{ route('plugin.update', ['plugin' => 'WeathermapNG']) }}" class="form-horizontal">
            @csrf
            
            <div class="form-group">
                <label class="col-sm-3 control-label">Poll Interval (seconds)</label>
                <div class="col-sm-9">
                    <input type="number" name="poll_interval" class="form-control" 
                           value="{{ $settings['poll_interval'] }}" min="60" max="3600">
                    <span class="help-block">How often to update map data (60-3600 seconds)</span>
                </div>
            </div>
            
            <div class="form-group">
                <label class="col-sm-3 control-label">Default Map Width</label>
                <div class="col-sm-9">
                    <input type="number" name="default_width" class="form-control" 
                           value="{{ $settings['default_width'] }}" min="400" max="2000">
                </div>
            </div>
            
            <div class="form-group">
                <label class="col-sm-3 control-label">Default Map Height</label>
                <div class="col-sm-9">
                    <input type="number" name="default_height" class="form-control" 
                           value="{{ $settings['default_height'] }}" min="300" max="1500">
                </div>
            </div>
            
            <div class="form-group">
                <label class="col-sm-3 control-label">RRD Base Path</label>
                <div class="col-sm-9">
                    <input type="text" name="rrd_base" class="form-control" 
                           value="{{ $settings['rrd_base'] }}">
                    <span class="help-block">Path to LibreNMS RRD files</span>
                </div>
            </div>
            
            <div class="form-group">
                <label class="col-sm-3 control-label">Cache TTL (seconds)</label>
                <div class="col-sm-9">
                    <input type="number" name="cache_ttl" class="form-control" 
                           value="{{ $settings['cache_ttl'] }}" min="0" max="3600">
                    <span class="help-block">How long to cache map data (0 to disable)</span>
                </div>
            </div>
            
            <div class="form-group">
                <label class="col-sm-3 control-label">Options</label>
                <div class="col-sm-9">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="enable_api_fallback" value="1" 
                                   @if($settings['enable_api_fallback']) checked @endif>
                            Enable API fallback when RRD files are not available
                        </label>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="allow_embed" value="1" 
                                   @if($settings['allow_embed']) checked @endif>
                            Allow embedding maps in external sites
                        </label>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="debug" value="1" 
                                   @if($settings['debug']) checked @endif>
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
                    <a href="{{ route('plugin.page', ['plugin' => 'WeathermapNG']) }}" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
