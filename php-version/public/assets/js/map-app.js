class WeespMapApp {
    constructor() {
        // Weesp coordinates
        this.centerLat = 52.3086;
        this.centerLng = 5.0408;
        this.markers = [];
        this.addMarkerMode = false;
        this.markerCounter = 0;
        this.authenticated = false;
        this.sessionToken = null;
        this.pendingMarkerLocation = null;
        this.tempPhotos = [];

        this.init();
    }

    init() {
        this.initAuth();
        this.initMap();
        this.bindEvents();
        this.loadMarkersFromServer();
        this.updateMarkerCount();
    }

    initAuth() {
        const authOverlay = document.getElementById('authOverlay');
        const pinInput = document.getElementById('pinInput');
        const loginBtn = document.getElementById('loginBtn');
        const cancelLoginBtn = document.getElementById('cancelLoginBtn');
        const authError = document.getElementById('authError');

        const attemptLogin = async () => {
            const enteredPin = pinInput.value;
            if (!enteredPin) {
                this.showAuthError('Please enter a PIN');
                return;
            }

            loginBtn.disabled = true;
            loginBtn.innerHTML = '<span class="loading"></span> Logging in...';

            try {
                const response = await fetch('/api/auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ pin: enteredPin })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    this.sessionToken = data.token;
                    this.authenticated = true;
                    authOverlay.classList.add('hidden');
                    this.updateAuthUI();
                    this.showStatus('✅ Authentication successful!');
                    pinInput.value = '';
                    authError.classList.add('hidden');
                } else {
                    this.showAuthError(data.error || 'Authentication failed');
                }
            } catch (error) {
                console.error('Login error:', error);
                this.showAuthError('Connection error. Please try again.');
            } finally {
                loginBtn.disabled = false;
                loginBtn.innerHTML = 'Login';
            }
        };

        const cancelLogin = () => {
            authOverlay.classList.add('hidden');
            pinInput.value = '';
            authError.classList.add('hidden');
        };

        loginBtn.addEventListener('click', attemptLogin);
        cancelLoginBtn.addEventListener('click', cancelLogin);
        pinInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') attemptLogin();
        });

        this.updateAuthUI();
    }

    showAuthError(message) {
        const authError = document.getElementById('authError');
        authError.textContent = message;
        authError.classList.remove('hidden');
        setTimeout(() => authError.classList.add('hidden'), 3000);
    }

    initMap() {
        // Initialize map centered on Weesp
        this.map = L.map('map').setView([this.centerLat, this.centerLng], 13);

        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(this.map);

        // Add a marker for Weesp center
        const weespMarker = L.marker([this.centerLat, this.centerLng])
            .addTo(this.map)
            .bindPopup(`
                <div class="marker-popup">
                    <div class="popup-header">📍 Weesp Center</div>
                    <div class="popup-info">Historic town center of Weesp</div>
                </div>
            `);

        // Bind map click event
        this.map.on('click', (e) => this.onMapClick(e));
    }

    bindEvents() {
        document.getElementById('loginToAddBtn').addEventListener('click', () => this.showLoginModal());
        document.getElementById('toggleMode').addEventListener('click', () => this.toggleAddMode());
        document.getElementById('clearMarkers').addEventListener('click', () => this.clearAllMarkers());
        document.getElementById('logoutBtn').addEventListener('click', () => this.logout());

        // Photo upload modal events
        document.getElementById('photoUploadArea').addEventListener('click', () => {
            document.getElementById('photoInput').click();
        });

        document.getElementById('photoInput').addEventListener('change', (e) => {
            this.handlePhotoSelection(e.target.files);
        });

        document.getElementById('savePhotos').addEventListener('click', () => this.saveMarkerWithPhotos());
        document.getElementById('cancelUpload').addEventListener('click', () => this.cancelPhotoUpload());

        // Drag and drop
        const uploadArea = document.getElementById('photoUploadArea');
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            this.handlePhotoSelection(e.dataTransfer.files);
        });

        // Photo overlay events
        document.getElementById('photoOverlay').addEventListener('click', (e) => {
            if (e.target.id === 'photoOverlay') {
                this.hidePhotoOverlay();
            }
        });

        document.getElementById('photoOverlayClose').addEventListener('click', () => {
            this.hidePhotoOverlay();
        });

        document.querySelector('.photo-overlay-content').addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Keyboard support for photo overlay
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const overlay = document.getElementById('photoOverlay');
                if (!overlay.classList.contains('hidden')) {
                    this.hidePhotoOverlay();
                }
            }
        });
    }

    showLoginModal() {
        const authOverlay = document.getElementById('authOverlay');
        const pinInput = document.getElementById('pinInput');
        authOverlay.classList.remove('hidden');
        pinInput.focus();
    }

    updateAuthUI() {
        const authOnlyElements = document.querySelectorAll('.auth-only');
        const loginToAddBtn = document.getElementById('loginToAddBtn');

        if (this.authenticated) {
            authOnlyElements.forEach(el => el.classList.remove('hidden'));
            loginToAddBtn.classList.add('hidden');
        } else {
            authOnlyElements.forEach(el => el.classList.add('hidden'));
            loginToAddBtn.classList.remove('hidden');
        }
    }

    showPhotoOverlay(photoSrc, photoName, markerInfo) {
        const overlay = document.getElementById('photoOverlay');
        const img = document.getElementById('photoOverlayImg');
        const info = document.getElementById('photoOverlayInfo');

        img.src = photoSrc;
        img.alt = photoName || 'Full size photo';
        info.textContent = markerInfo || 'Click anywhere outside to close | Press ESC to close';

        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    hidePhotoOverlay() {
        const overlay = document.getElementById('photoOverlay');
        const img = document.getElementById('photoOverlayImg');

        overlay.classList.add('hidden');
        img.src = '';
        document.body.style.overflow = '';
    }

    onMapClick(e) {
        if (!this.authenticated) {
            this.showStatus('🔐 Login required to add markers');
            this.showLoginModal();
            return;
        }

        if (!this.addMarkerMode) return;

        this.pendingMarkerLocation = { lat: e.latlng.lat, lng: e.latlng.lng };
        this.showPhotoUploadModal();
    }

    showPhotoUploadModal() {
        this.tempPhotos = [];
        document.getElementById('photoUploadModal').classList.remove('hidden');
        document.getElementById('photoPreview').innerHTML = '';
    }

    handlePhotoSelection(files) {
        const maxPhotos = 2;
        const maxSize = 5 * 1024 * 1024; // 5MB

        if (this.tempPhotos.length >= maxPhotos) {
            this.showStatus('❌ Maximum 2 photos allowed per marker');
            return;
        }

        Array.from(files).slice(0, maxPhotos - this.tempPhotos.length).forEach(file => {
            if (file.size > maxSize) {
                this.showStatus(`❌ ${file.name} is too large (max 5MB)`);
                return;
            }

            if (!file.type.startsWith('image/')) {
                this.showStatus(`❌ ${file.name} is not a valid image`);
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                this.tempPhotos.push({
                    name: file.name,
                    data: e.target.result,
                    size: file.size,
                    file: file // Keep original file for upload
                });
                this.updatePhotoPreview();
            };
            reader.readAsDataURL(file);
        });
    }

    updatePhotoPreview() {
        const preview = document.getElementById('photoPreview');
        preview.innerHTML = '';

        this.tempPhotos.forEach((photo, index) => {
            const div = document.createElement('div');
            div.className = 'photo-preview-item';
            div.innerHTML = `
                <img src="${photo.data}" alt="${photo.name}">
                <button class="photo-remove" onclick="app.removePhoto(${index})">×</button>
            `;
            preview.appendChild(div);
        });
    }

    removePhoto(index) {
        this.tempPhotos.splice(index, 1);
        this.updatePhotoPreview();
    }

    async saveMarkerWithPhotos() {
        if (this.tempPhotos.length === 0) {
            this.showStatus('❌ Please add at least one photo');
            return;
        }

        const saveBtn = document.getElementById('savePhotos');
        const originalText = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="loading"></span> Saving...';

        try {
            const formData = new FormData();
            formData.append('lat', this.pendingMarkerLocation.lat);
            formData.append('lng', this.pendingMarkerLocation.lng);

            // Add photos to form data
            this.tempPhotos.forEach((photo, index) => {
                formData.append('photos[]', photo.file);
            });

            const response = await fetch('/api/markers.php', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.sessionToken}`
                },
                body: formData
            });

            const data = await response.json();

            if (response.ok && data.success) {
                this.addMarkerToMap(data.marker);
                this.cancelPhotoUpload();
                this.showStatus('✅ Marker with photos added!');

                if (data.warnings && data.warnings.length > 0) {
                    setTimeout(() => {
                        this.showStatus('⚠️ Some photos had issues: ' + data.warnings.join(', '), 'warning');
                    }, 2000);
                }
            } else {
                throw new Error(data.error || 'Failed to save marker');
            }
        } catch (error) {
            console.error('Save marker error:', error);
            this.showStatus('❌ Failed to save marker: ' + error.message, 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
    }

    cancelPhotoUpload() {
        document.getElementById('photoUploadModal').classList.add('hidden');
        this.tempPhotos = [];
        this.pendingMarkerLocation = null;
        document.getElementById('photoInput').value = '';
    }

    addMarkerToMap(markerData) {
        // Create custom marker with a different color
        const customIcon = L.divIcon({
            className: 'custom-marker',
            html: `<div style="
                background: #e74c3c;
                border: 3px solid white;
                border-radius: 50%;
                width: 20px;
                height: 20px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            "></div>`,
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });

        const marker = L.marker([markerData.lat, markerData.lng], { icon: customIcon }).addTo(this.map);

        // Create popup with uploaded photos
        let photoGalleryHtml = '';
        if (markerData.photos && markerData.photos.length > 0) {
            photoGalleryHtml = '<div class="photo-gallery">';
            markerData.photos.forEach((photo, index) => {
                photoGalleryHtml += `<img src="${photo.url}" alt="${photo.original_name}" onclick="app.showPhotoOverlay('${photo.url}', '${photo.original_name}', 'Photo from Marker ${markerData.id}')">`;
            });
            photoGalleryHtml += '</div>';
        } else {
            photoGalleryHtml = '<div class="popup-info"><em>No photos uploaded</em></div>';
        }

        const popupContent = `
            <div class="marker-popup">
                <div class="popup-header">📸 Photo Location ${markerData.id}</div>
                ${photoGalleryHtml}
                <div class="popup-info">
                    <strong>Coordinates:</strong> ${markerData.lat.toFixed(5)}, ${markerData.lng.toFixed(5)}<br>
                    <strong>Photos:</strong> ${markerData.photos ? markerData.photos.length : 0}/2<br>
                    <small>Click photos to view full size</small>
                </div>
            </div>
        `;

        marker.bindPopup(popupContent);

        this.markers.push({
            marker: marker,
            id: markerData.id,
            lat: markerData.lat,
            lng: markerData.lng,
            photos: markerData.photos || [],
            created_at: markerData.created_at
        });

        this.updateMarkerCount();
    }

    async loadMarkersFromServer() {
        try {
            this.showStatus('Loading markers...', 'info');

            const response = await fetch('/api/markers.php');
            const data = await response.json();

            if (response.ok) {
                this.markerCounter = data.counter;

                // Clear existing markers (except Weesp center)
                this.markers.forEach(markerObj => {
                    this.map.removeLayer(markerObj.marker);
                });
                this.markers = [];

                // Add markers from server
                data.markers.forEach(markerData => {
                    this.addMarkerToMap(markerData);
                });

                this.updateMarkerCount();

                if (data.markers.length > 0) {
                    this.showStatus(`✅ Loaded ${data.markers.length} markers`);
                } else {
                    // Don't show a message for empty state on initial load
                }
            } else {
                throw new Error(data.error || 'Failed to load markers');
            }
        } catch (error) {
            console.error('Load markers error:', error);
            this.showStatus('❌ Failed to load markers', 'error');
        }
    }

    toggleAddMode() {
        if (!this.authenticated) {
            this.showStatus('❌ Authentication required');
            return;
        }

        this.addMarkerMode = !this.addMarkerMode;
        const btn = document.getElementById('toggleMode');

        if (this.addMarkerMode) {
            btn.textContent = '🎯 Add Marker Mode: ON';
            btn.classList.add('active');
            this.map.getContainer().style.cursor = 'crosshair';
            this.showStatus('Click anywhere on the map to add a marker with photos 🎯');
        } else {
            btn.textContent = '🎯 Add Marker Mode: OFF';
            btn.classList.remove('active');
            this.map.getContainer().style.cursor = '';
            this.showStatus('Marker mode disabled');
        }
    }

    async clearAllMarkers() {
        if (!this.authenticated) {
            this.showStatus('❌ Authentication required');
            return;
        }

        if (this.markers.length === 0) {
            this.showStatus('No markers to clear');
            return;
        }

        if (!confirm(`Are you sure you want to remove all ${this.markers.length} markers and their photos?`)) {
            return;
        }

        const clearBtn = document.getElementById('clearMarkers');
        const originalText = clearBtn.innerHTML;
        clearBtn.disabled = true;
        clearBtn.innerHTML = '<span class="loading"></span> Clearing...';

        try {
            const response = await fetch('/api/markers.php?all=1', {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${this.sessionToken}`
                }
            });

            const data = await response.json();

            if (response.ok && data.success) {
                // Remove markers from map
                this.markers.forEach(markerObj => {
                    this.map.removeLayer(markerObj.marker);
                });

                this.markers = [];
                this.markerCounter = 0;
                this.updateMarkerCount();
                this.showStatus('All markers and photos cleared! 🗑️');
            } else {
                throw new Error(data.error || 'Failed to clear markers');
            }
        } catch (error) {
            console.error('Clear markers error:', error);
            this.showStatus('❌ Failed to clear markers: ' + error.message, 'error');
        } finally {
            clearBtn.disabled = false;
            clearBtn.innerHTML = originalText;
        }
    }

    async logout() {
        const logoutBtn = document.getElementById('logoutBtn');
        const originalText = logoutBtn.innerHTML;
        logoutBtn.disabled = true;
        logoutBtn.innerHTML = '<span class="loading"></span> Logging out...';

        try {
            await fetch('/api/auth.php', {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${this.sessionToken}`
                }
            });
        } catch (error) {
            // Logout errors are not critical
            console.warn('Logout error:', error);
        } finally {
            this.authenticated = false;
            this.sessionToken = null;
            this.addMarkerMode = false;
            document.getElementById('toggleMode').textContent = '🎯 Add Marker Mode: OFF';
            document.getElementById('toggleMode').classList.remove('active');
            this.map.getContainer().style.cursor = '';
            this.updateAuthUI();
            this.showStatus('Logged out successfully');

            logoutBtn.disabled = false;
            logoutBtn.innerHTML = originalText;
        }
    }

    updateMarkerCount() {
        document.getElementById('markerCount').textContent = this.markers.length;
    }

    showStatus(message, type = 'success') {
        const status = document.getElementById('status');
        status.textContent = message;
        status.className = 'status show';

        if (type === 'error') {
            status.classList.add('error');
        } else if (type === 'warning') {
            status.classList.add('warning');
        }

        setTimeout(() => {
            status.classList.remove('show');
            setTimeout(() => {
                status.className = 'status'; // Reset classes
            }, 300);
        }, 3000);
    }
}

// Initialize the app when the page loads
let app;
document.addEventListener('DOMContentLoaded', () => {
    app = new WeespMapApp();
});