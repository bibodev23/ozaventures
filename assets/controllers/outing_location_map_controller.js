import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.min.css';

const DEFAULT_CENTER = [46.817, 0.546];

export default class extends Controller {
    static targets = ['map', 'status', 'list'];
    static values = {
        url: String,
        interval: { type: Number, default: 10000 },
    };

    connect() {
        this.markers = new Map();
        this.map = L.map(this.mapTarget, {
            zoomControl: true,
            scrollWheelZoom: false,
        }).setView(DEFAULT_CENTER, 12);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(this.map);

        this.refresh();
        this.timer = window.setInterval(() => this.refresh(), this.intervalValue);
    }

    disconnect() {
        if (this.timer) {
            window.clearInterval(this.timer);
        }

        if (this.map) {
            this.map.remove();
        }
    }

    async refresh() {
        if (!this.hasUrlValue) {
            return;
        }

        try {
            const response = await fetch(this.urlValue, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            this.renderLocations(Array.isArray(payload.locations) ? payload.locations : []);
        } catch (error) {
            this.statusTarget.textContent = 'Impossible de récupérer les positions pour le moment.';
        }
    }

    renderLocations(locations) {
        const activeKeys = new Set();
        const bounds = [];

        locations.forEach((location) => {
            const lat = Number(location.latitude);
            const lng = Number(location.longitude);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }

            const animator = location.animator || {};
            const key = String(animator.id || `${lat}-${lng}`);
            activeKeys.add(key);
            bounds.push([lat, lng]);

            const marker = this.markers.get(key) || L.marker([lat, lng], {
                icon: this.markerIcon(animator),
            }).addTo(this.map);

            marker
                .setLatLng([lat, lng])
                .bindPopup(this.popupLabel(animator, location));
            this.markers.set(key, marker);
        });

        this.markers.forEach((marker, key) => {
            if (!activeKeys.has(key)) {
                marker.remove();
                this.markers.delete(key);
            }
        });

        if (bounds.length > 0) {
            this.map.fitBounds(bounds, { padding: [42, 42], maxZoom: 16 });
            this.statusTarget.textContent = `${bounds.length} position(s) active(s). Mise à jour automatique toutes les 10 secondes.`;
        } else {
            this.map.setView(DEFAULT_CENTER, 12);
            this.statusTarget.textContent = 'En attente du premier partage de position depuis l’app mobile.';
        }

        this.renderList(locations);
    }

    markerIcon(animator) {
        const initials = this.initials(animator);

        return L.divIcon({
            className: 'outing-location-marker',
            html: `<span>${this.escape(initials)}</span>`,
            iconSize: [42, 42],
            iconAnchor: [21, 21],
        });
    }

    popupLabel(animator, location) {
        const name = animator.fullName || 'Animateur';
        const accuracy = location.accuracy ? ` · précision ${Math.round(location.accuracy)} m` : '';

        return `${this.escape(name)}<br><small>${this.formatDate(location.recordedAt)}${accuracy}</small>`;
    }

    renderList(locations) {
        this.listTarget.replaceChildren();

        if (locations.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'empty-state empty-state-soft';
            empty.innerHTML = '<strong>Aucune position pour le moment.</strong><span>Le partage apparaîtra ici dès qu’un animateur l’active.</span>';
            this.listTarget.append(empty);
            return;
        }

        locations.forEach((location) => {
            const animator = location.animator || {};
            const row = document.createElement('article');
            row.className = 'outing-location-row';

            const avatar = document.createElement('span');
            avatar.className = 'outing-location-avatar';
            avatar.textContent = this.initials(animator);

            const content = document.createElement('span');
            content.className = 'outing-location-copy';

            const name = document.createElement('strong');
            name.textContent = animator.fullName || 'Animateur';

            const meta = document.createElement('small');
            meta.textContent = this.locationMeta(location);

            content.append(name, meta);
            row.append(avatar, content);
            this.listTarget.append(row);
        });
    }

    locationMeta(location) {
        const accuracy = location.accuracy ? ` · précision ${Math.round(location.accuracy)} m` : '';

        return `${this.formatDate(location.recordedAt)}${accuracy}`;
    }

    initials(animator) {
        const first = (animator.firstName || 'O').trim().charAt(0);
        const last = (animator.lastName || 'Z').trim().charAt(0);

        return `${first}${last}`.toUpperCase();
    }

    formatDate(value) {
        if (!value) {
            return 'heure inconnue';
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return new Intl.DateTimeFormat('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    }

    escape(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
}
