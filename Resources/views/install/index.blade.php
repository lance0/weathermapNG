@extends('layouts.app')

@section('title', 'WeathermapNG - Installation')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="fas fa-cog"></i> WeathermapNG Installation</h3>
                </div>
                <div class="card-body">
                    <!-- Requirements Check -->
                    <div class="mb-4">
                        <h5><i class="fas fa-check-circle"></i> System Requirements</h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Component</th>
                                        <th>Required</th>
                                        <th>Current</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requirements as $req)
                                    <tr>
                                        <td>{{ $req['name'] }}</td>
                                        <td>{{ $req['required'] }}</td>
                                        <td>{{ $req['current'] }}</td>
                                        <td>
                                            @if($req['status'])
                                                <span class="badge bg-success"><i class="fas fa-check"></i> OK</span>
                                            @else
                                                <span class="badge bg-danger"><i class="fas fa-times"></i> Failed</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Installation Steps -->
                    <div class="mb-4">
                        <h5><i class="fas fa-list-check"></i> Installation Progress</h5>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 0%" id="installProgress"></div>
                        </div>
                        <ul class="list-group" id="installSteps">
                            <li class="list-group-item d-flex align-items-center" data-step="requirements">
                                @if($steps['requirements'])
                                    <i class="fas fa-check text-success me-2"></i>
                                @else
                                    <i class="fas fa-times text-danger me-2"></i>
                                @endif
                                Check System Requirements
                            </li>
                            <li class="list-group-item d-flex align-items-center" data-step="database">
                                <i class="fas fa-clock text-warning me-2" id="step-database-icon"></i>
                                Set Up Database Tables
                            </li>
                            <li class="list-group-item d-flex align-items-center" data-step="permissions">
                                <i class="fas fa-clock text-warning me-2" id="step-permissions-icon"></i>
                                Configure Permissions
                            </li>
                            <li class="list-group-item d-flex align-items-center" data-step="plugin">
                                <i class="fas fa-clock text-warning me-2" id="step-plugin-icon"></i>
                                Enable Plugin
                            </li>
                        </ul>
                    </div>

                    <!-- Install Button -->
                    <div class="text-center">
                        @if($steps['requirements'])
                            <button class="btn btn-primary btn-lg" id="installBtn" onclick="startInstallation()">
                                <i class="fas fa-play"></i> Start Installation
                            </button>
                        @else
                            <button class="btn btn-secondary btn-lg" disabled>
                                <i class="fas fa-exclamation-triangle"></i> Fix Requirements First
                            </button>
                        @endif
                        <div id="installSpinner" class="d-none mt-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Installing...</span>
                            </div>
                            <span class="ms-2">Installing WeathermapNG...</span>
                        </div>
                    </div>

                    <!-- Alternative Installation -->
                    <div class="mt-4">
                        <h6><i class="fas fa-terminal"></i> Alternative: Command Line Installation</h6>
                        <p class="text-muted small">For advanced users or automated deployments:</p>
                        <div class="bg-light p-3 rounded">
                            <code>cd /opt/librenms/html/plugins/WeathermapNG<br>./install.sh</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> Installation Complete!</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">WeathermapNG has been successfully installed!</p>
                <div class="alert alert-info">
                    <strong>Next steps:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Create your first network map</li>
                        <li>Add devices from LibreNMS</li>
                        <li>Configure map polling (every 5 minutes)</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="goToPlugin()">
                    <i class="fas fa-map"></i> Go to WeathermapNG
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Installation Failed</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="errorMessage">An error occurred during installation.</p>
                <div class="alert alert-warning">
                    <strong>Troubleshooting:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Check file permissions</li>
                        <li>Verify database credentials</li>
                        <li>Ensure PHP extensions are installed</li>
                        <li>Check /var/log/librenms/weathermapng_install.log</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="retryInstallation()">
                    <i class="fas fa-redo"></i> Retry
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function startInstallation() {
    const btn = document.getElementById('installBtn');
    const spinner = document.getElementById('installSpinner');

    btn.classList.add('d-none');
    spinner.classList.remove('d-none');

    fetch('{{ route("weathermapng.install.post") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        spinner.classList.add('d-none');

        if (data.success) {
            // Update progress
            updateProgress(100);
            updateStepStatus('database', true);
            updateStepStatus('permissions', true);
            updateStepStatus('plugin', true);

            // Show success modal
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
        } else {
            // Show error modal
            document.getElementById('errorMessage').textContent = data.message;
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            errorModal.show();

            btn.classList.remove('d-none');
        }
    })
    .catch(error => {
        spinner.classList.add('d-none');
        btn.classList.remove('d-none');

        document.getElementById('errorMessage').textContent = 'Network error: ' + error.message;
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();
    });
}

function updateProgress(percent) {
    document.getElementById('installProgress').style.width = percent + '%';
}

function updateStepStatus(step, success) {
    const icon = document.getElementById('step-' + step + '-icon');
    icon.className = success ? 'fas fa-check text-success me-2' : 'fas fa-times text-danger me-2';
}

function goToPlugin() {
    window.location.href = '{{ url("/plugins/weathermapng") }}';
}

function retryInstallation() {
    // Hide error modal and retry
    bootstrap.Modal.getInstance(document.getElementById('errorModal')).hide();
    startInstallation();
}

// Auto-check requirements on page load
document.addEventListener('DOMContentLoaded', function() {
    // Could add real-time requirement checking here
});
</script>
@endsection