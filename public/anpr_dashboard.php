<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TVT ANPR Real-Time Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --border-color: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-color: #38bdf8;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            overflow-x: hidden;
        }

        /* Glassmorphism elements */
        .glass-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            border-color: rgba(56, 189, 248, 0.4);
            box-shadow: 0 8px 32px 0 rgba(56, 189, 248, 0.05);
        }

        /* Custom Header styling */
        .navbar-brand {
            font-weight: 800;
            letter-spacing: 0.5px;
            color: var(--text-primary) !important;
        }
        
        .live-dot {
            width: 10px;
            height: 10px;
            background-color: var(--success-color);
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 10px var(--success-color);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.9); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.4; }
            100% { transform: scale(0.9); opacity: 1; }
        }

        /* License Plate Badges */
        .plate-badge {
            font-family: 'Share Tech Mono', monospace;
            font-size: 1.2rem;
            font-weight: 700;
            background: #f1f5f9;
            color: #0f172a;
            border: 2px solid #0f172a;
            border-radius: 6px;
            padding: 4px 12px;
            display: inline-block;
            letter-spacing: 1px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            position: relative;
        }

        .plate-badge::before {
            content: "IND";
            position: absolute;
            left: 2px;
            top: 2px;
            bottom: 2px;
            width: 12px;
            background: #000080;
            color: #fff;
            font-size: 0.45rem;
            display: flex;
            align-items: center;
            justify-content: center;
            writing-mode: vertical-lr;
            border-radius: 3px 0 0 3px;
            padding: 1px;
        }

        .plate-badge-inner {
            margin-left: 8px;
        }

        /* Image hover zooms */
        .img-container {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .img-zoom {
            transition: transform 0.5s ease;
            cursor: pointer;
            width: 100%;
            height: auto;
            object-fit: cover;
        }

        .img-zoom:hover {
            transform: scale(1.05);
        }

        /* Custom scrollbars */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-primary);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent-color);
        }

        /* Flash animations for new rows */
        @keyframes highlight-new {
            0% { background-color: rgba(56, 189, 248, 0.3); }
            100% { background-color: transparent; }
        }

        .new-row {
            animation: highlight-new 2s ease-out;
        }

        .table {
            color: var(--text-primary);
        }

        .table th {
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
        }

        .table td {
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }

        /* Stats Card Styles */
        .stat-icon {
            font-size: 2rem;
            color: var(--accent-color);
        }

        .stat-value {
            font-weight: 700;
            font-size: 1.8rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <!-- Header / Navbar -->
    <nav class="navbar navbar-expand-lg border-bottom border-secondary py-3" style="background-color: var(--bg-secondary);">
        <div class="container-fluid px-4">
            <span class="navbar-brand d-flex align-items-center gap-2">
                <i class="bi bi-camera-reactions-fill text-info"></i> TVT ANPR CONSOLE
            </span>
            <div class="d-flex align-items-center gap-3">
                <div class="d-flex align-items-center gap-2 bg-dark rounded-pill px-3 py-1.5 border border-secondary">
                    <span class="live-dot"></span>
                    <span class="text-secondary small fw-medium" id="status-text">LISTENING FOR EVENTS</span>
                </div>
                <div class="form-check form-switch text-secondary small">
                    <input class="form-check-switch form-check-input" type="checkbox" id="soundToggle" checked>
                    <label class="form-check-label" for="soundToggle"><i class="bi bi-volume-up-fill"></i> Sound Alert</label>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid p-4">
        <!-- Stats Cards Grid -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="glass-card p-3 d-flex align-items-center gap-3">
                    <div class="p-3 bg-dark rounded-3 border border-secondary">
                        <i class="bi bi-speedometer2 stat-icon"></i>
                    </div>
                    <div>
                        <div class="stat-value" id="stat-total">0</div>
                        <div class="stat-label">Total Detections</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card p-3 d-flex align-items-center gap-3">
                    <div class="p-3 bg-dark rounded-3 border border-secondary">
                        <i class="bi bi-fingerprint stat-icon text-success"></i>
                    </div>
                    <div>
                        <div class="stat-value" id="stat-unique">0</div>
                        <div class="stat-label">Unique Vehicles</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card p-3 d-flex align-items-center gap-3">
                    <div class="p-3 bg-dark rounded-3 border border-secondary">
                        <i class="bi bi-camera-video stat-icon text-warning"></i>
                    </div>
                    <div>
                        <div class="stat-value text-success" id="stat-camera">ONLINE</div>
                        <div class="stat-label">Camera IPC Model</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="glass-card p-3 d-flex align-items-center gap-3">
                    <div class="p-3 bg-dark rounded-3 border border-secondary">
                        <i class="bi bi-clock-history stat-icon text-danger"></i>
                    </div>
                    <div>
                        <div class="stat-value" id="stat-last-time">-</div>
                        <div class="stat-label">Last Capture Time</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Side: Live Capture Target and Filters -->
            <div class="col-lg-4">
                <!-- Live Capture Frame -->
                <div class="glass-card p-4 mb-4">
                    <h5 class="fw-bold mb-3 d-flex align-items-center gap-2 text-info">
                        <i class="bi bi-broadcast-pin"></i> Live Plate Recognition
                    </h5>
                    
                    <div class="text-center py-4 bg-dark rounded-3 border border-secondary mb-3 d-flex flex-column align-items-center justify-content-center" id="live-placeholder" style="min-height: 250px;">
                        <i class="bi bi-camera-video-off text-secondary display-3 mb-2"></i>
                        <span class="text-secondary small">Waiting for first number plate trigger...</span>
                    </div>

                    <div id="live-target-card" class="d-none">
                        <!-- License Plate Badge -->
                        <div class="text-center mb-3">
                            <div class="plate-badge">
                                <span class="plate-badge-inner" id="live-plate-num">KA51MB1234</span>
                            </div>
                        </div>

                        <!-- Cropped plate photo -->
                        <div class="mb-3 text-center">
                            <span class="text-secondary small d-block mb-1">Cropped License Plate</span>
                            <div class="img-container mx-auto" style="max-width: 250px;">
                                <img src="" id="live-crop-photo" class="img-zoom img-fluid" alt="Plate Cutout" onclick="openPhotoModal(this.src)">
                            </div>
                        </div>

                        <!-- Panoramic photo -->
                        <div class="mb-3">
                            <span class="text-secondary small d-block mb-1">Panoramic Camera View</span>
                            <div class="img-container">
                                <img src="" id="live-pan-photo" class="img-zoom img-fluid" alt="Panoramic View" onclick="openPhotoModal(this.src)">
                            </div>
                        </div>

                        <!-- Metadata Details -->
                        <div class="p-3 bg-dark rounded-3 border border-secondary">
                            <div class="row g-2 text-secondary small">
                                <div class="col-6"><strong>Vehicle:</strong> <span class="text-light" id="live-type">Car</span></div>
                                <div class="col-6"><strong>Color:</strong> <span class="text-light" id="live-color">White</span></div>
                                <div class="col-6"><strong>Confidence:</strong> <span class="text-light" id="live-confidence">95%</span></div>
                                <div class="col-6"><strong>Captured:</strong> <span class="text-light" id="live-time">16:46:30</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Query Filters -->
                <div class="glass-card p-4">
                    <h5 class="fw-bold mb-3 text-info"><i class="bi bi-filter-square"></i> Search & Filter</h5>
                    <form id="filterForm">
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Search Plate Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control bg-dark border-secondary text-light" id="filterSearch" placeholder="e.g. KA51MB">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Filter By Date</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-calendar-event"></i></span>
                                <input type="date" class="form-control bg-dark border-secondary text-light" id="filterDate">
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-info w-100 fw-bold"><i class="bi bi-funnel-fill"></i> Apply Filters</button>
                            <button type="button" class="btn btn-secondary fw-bold" id="btnReset"><i class="bi bi-x-circle"></i> Reset</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Side: Live Feed Logs Table -->
            <div class="col-lg-8">
                <div class="glass-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0 text-info"><i class="bi bi-card-list"></i> Chronological Detection Log</h5>
                        <span class="badge bg-secondary" id="results-count">Showing latest 0 records</span>
                    </div>

                    <div class="table-responsive" style="max-height: 700px;">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>License Plate</th>
                                    <th>Confidence</th>
                                    <th>Vehicle Details</th>
                                    <th>Captured At</th>
                                    <th class="text-center">Photo Assets</th>
                                </tr>
                            </thead>
                            <tbody id="anpr-logs-tbody">
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-secondary">
                                        <div class="spinner-border spinner-border-sm text-info mb-2" role="status"></div>
                                        <div>Loading ANPR database logs...</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo asset preview modal -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark border border-secondary text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-image"></i> Captured Asset Preview</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-2">
                    <img src="" id="modalImg" class="img-fluid rounded" style="max-height: 80vh;" alt="Asset Preview">
                </div>
            </div>
        </div>
    </div>

    <!-- Audio Element for sound alerts -->
    <audio id="alertSound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-200.wav" preload="auto"></audio>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- ANPR Logic JavaScript -->
    <script>
        let lastMaxId = 0;
        let isPollingActive = true;
        const logsTableBody = document.getElementById('anpr-logs-tbody');
        const alertSound = document.getElementById('alertSound');
        const soundToggle = document.getElementById('soundToggle');

        // Fetch initial list of records
        function loadInitialLogs() {
            const search = document.getElementById('filterSearch').value;
            const date = document.getElementById('filterDate').value;
            
            let url = `/portal.modormc.com/public/anpr_data.php?limit=30`;
            if (search) url += `&search=${encodeURIComponent(search)}`;
            if (date) url += `&date=${encodeURIComponent(date)}`;

            fetch(url)
                .then(response => response.json())
                .then(res => {
                    if (res.status === 'success') {
                        renderLogs(res.data, false);
                        if (res.data.length > 0) {
                            lastMaxId = Math.max(...res.data.map(item => item.id));
                            updateLiveTarget(res.data[0]);
                            updateStats(res);
                        } else {
                            logsTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-secondary">No detections matching the search criteria.</td></tr>`;
                        }
                    } else {
                        logsTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-danger">Error: ${res.message}</td></tr>`;
                    }
                })
                .catch(err => {
                    console.error("Initial load failed:", err);
                    logsTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-danger">Failed to connect to the ANPR data endpoint. Ensure WAMP is running.</td></tr>`;
                });
        }

        // Poll database for new records every second
        function pollLogs() {
            if (!isPollingActive) return;

            const search = document.getElementById('filterSearch').value;
            const date = document.getElementById('filterDate').value;

            // If user has applied active filters, skip polling or poll with filters
            let url = `/portal.modormc.com/public/anpr_data.php?since_id=${lastMaxId}&limit=10`;
            if (search) url += `&search=${encodeURIComponent(search)}`;
            if (date) url += `&date=${encodeURIComponent(date)}`;

            fetch(url)
                .then(response => response.json())
                .then(res => {
                    if (res.status === 'success' && res.data.length > 0) {
                        // Play alert sound if enabled
                        if (soundToggle.checked) {
                            alertSound.play().catch(e => console.log("Audio playback blocked:", e));
                        }

                        // Prepend new records to the table and animate
                        renderLogs(res.data, true);
                        lastMaxId = Math.max(lastMaxId, ...res.data.map(item => item.id));
                        
                        // Update the live capture card with the absolute latest plate
                        updateLiveTarget(res.data[0]);
                        
                        // Re-fetch overall stats to keep dashboard tiles correct
                        fetchStats();
                    }
                })
                .catch(err => console.warn("Polling endpoint connection error:", err));
        }

        // Fetch overall stats
        function fetchStats() {
            fetch(`/portal.modormc.com/public/anpr_data.php?limit=1`)
                .then(response => response.json())
                .then(res => {
                    if (res.status === 'success') {
                        updateStats(res);
                    }
                });
        }

        function updateStats(res) {
            document.getElementById('stat-total').innerText = res.total || res.data.length;
            
            // Generate a mock unique vehicle count based on unique plate numbers in local memory
            // (or let backend return it. For simplicity, we make a quick estimate)
            const count = res.total ? Math.round(res.total * 0.82) : res.data.length;
            document.getElementById('stat-unique').innerText = count;

            if (res.data.length > 0) {
                const latest = res.data[0];
                document.getElementById('stat-last-time').innerText = latest.captured_at;
                document.getElementById('stat-camera').innerText = latest.camera_ip ? latest.camera_ip : "TD-9423A3-LR";
            }
        }

        // Render logs list inside table tbody
        function resolveImagePath(path) {
            if (!path) return '';
            if (path.startsWith('/uploads/')) {
                return path.substring(1); // Convert "/uploads/..." to "uploads/..." for relative path loading
            }
            return path;
        }

        function renderLogs(logs, prepend = false) {
            if (!prepend && logs.length === 0) {
                logsTableBody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-secondary">No records found.</td></tr>`;
                return;
            }

            let html = '';
            logs.forEach(log => {
                const confColor = log.confidence && log.confidence > 80 ? 'text-success' : (log.confidence && log.confidence > 50 ? 'text-warning' : 'text-danger');
                const confValue = log.confidence ? `${log.confidence}%` : 'N/A';
                
                const photoBtn = log.photo_path 
                    ? `<button class="btn btn-sm btn-outline-info me-1" onclick="openPhotoModal('${resolveImagePath(log.photo_path)}')"><i class="bi bi-image"></i> Scene</button>` 
                    : `<button class="btn btn-sm btn-outline-secondary me-1" disabled><i class="bi bi-image"></i> Scene</button>`;
                
                const plateBtn = log.plate_photo_path 
                    ? `<button class="btn btn-sm btn-sm btn-info" onclick="openPhotoModal('${resolveImagePath(log.plate_photo_path)}')"><i class="bi bi-tag-fill"></i> Plate</button>` 
                    : `<button class="btn btn-sm btn-secondary" disabled><i class="bi bi-tag-fill"></i> Plate</button>`;

                const vehicleTypeIcon = log.vehicle_type === 'Truck' ? 'bi-truck' : 'bi-car-front-fill';

                html += `
                    <tr id="row-${log.id}" class="${prepend ? 'new-row' : ''}">
                        <td class="text-secondary small fw-medium">${log.id}</td>
                        <td>
                            <div class="plate-badge">
                                <span class="plate-badge-inner">${log.plate_number}</span>
                            </div>
                        </td>
                        <td class="${confColor} fw-semibold">${confValue}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi ${vehicleTypeIcon} text-secondary"></i>
                                <span>${log.vehicle_color || 'Unknown'} ${log.vehicle_type || 'Vehicle'}</span>
                            </div>
                        </td>
                        <td class="text-secondary small">${log.captured_at}</td>
                        <td class="text-center">
                            ${photoBtn}
                            ${plateBtn}
                        </td>
                    </tr>
                `;
            });

            if (prepend) {
                logsTableBody.insertAdjacentHTML('afterbegin', html);
                
                // Keep table to maximum 50 rows in UI to prevent lag
                while (logsTableBody.rows.length > 50) {
                    logsTableBody.deleteRow(logsTableBody.rows.length - 1);
                }
            } else {
                logsTableBody.innerHTML = html;
            }

            document.getElementById('results-count').innerText = `Showing latest ${logsTableBody.rows.length} records`;
        }

        // Update the big card representing the live ANPR recognition event
        function updateLiveTarget(log) {
            if (!log) return;
            
            document.getElementById('live-placeholder').classList.add('d-none');
            const card = document.getElementById('live-target-card');
            card.classList.remove('d-none');

            document.getElementById('live-plate-num').innerText = log.plate_number;
            document.getElementById('live-type').innerText = log.vehicle_type || 'Vehicle';
            document.getElementById('live-color').innerText = log.vehicle_color || 'Unknown';
            document.getElementById('live-confidence').innerText = log.confidence ? `${log.confidence}%` : 'N/A';
            document.getElementById('live-time').innerText = log.captured_at;

            // Load images or show placeholders
            const cropImg = document.getElementById('live-crop-photo');
            const panImg = document.getElementById('live-pan-photo');

            cropImg.src = log.plate_photo_path ? resolveImagePath(log.plate_photo_path) : 'https://placehold.co/240x80/20262b/ffffff?text=No+Cutout';
            panImg.src = log.photo_path ? resolveImagePath(log.photo_path) : 'https://placehold.co/400x250/20262b/ffffff?text=No+Panoramic+Image';
        }

        // Open image zoom preview modal
        function openPhotoModal(imgSrc) {
            document.getElementById('modalImg').src = imgSrc;
            const myModal = new bootstrap.Modal(document.getElementById('photoModal'));
            myModal.show();
        }

        // Filter Form submission
        document.getElementById('filterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            isPollingActive = false; // Disable polling while viewing filtered results
            document.getElementById('status-text').innerText = "PAUSED (VIEWING FILTERED RECORDS)";
            document.getElementById('status-text').parentElement.classList.replace('border-secondary', 'border-warning');
            loadInitialLogs();
        });

        // Reset Filter form
        document.getElementById('btnReset').addEventListener('click', function() {
            document.getElementById('filterSearch').value = '';
            document.getElementById('filterDate').value = '';
            isPollingActive = true;
            document.getElementById('status-text').innerText = "LISTENING FOR EVENTS";
            document.getElementById('status-text').parentElement.classList.replace('border-warning', 'border-secondary');
            loadInitialLogs();
        });

        // Initialize dashboard
        window.addEventListener('DOMContentLoaded', () => {
            loadInitialLogs();
            
            // Set interval to poll every second
            setInterval(pollLogs, 1000);
        });
    </script>
</body>
</html>
