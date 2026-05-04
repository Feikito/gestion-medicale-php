document.addEventListener('DOMContentLoaded', () => {
    initMobileNav();
    initFooterYear();
    initDismissAlerts();
    initOpenModalFromUrl();
    initModals();
    initAllFormValidations();

    if (document.body.classList.contains('body-index')) {
        initIndexPage();
    }
    if (document.body.classList.contains('body-page-doctors')) {
        initDoctorsPage();
    }
    if (document.body.classList.contains('rdv-page')) {
        initRdvPage();
    }
    if (document.body.classList.contains('body-page-faq')) {
        initFaqAccordion();
    }
    if (document.body.classList.contains('body-page-mes-rdv')) {
        initMesRdvPatientPageModals();
    }
    if (document.body.classList.contains('body-page-mes-rdv-medecin')) {
        initMesRdvMedecinPageModals();
    }
    if (document.body.classList.contains('profile-page')) {
        initProfilePage();
    }
    if (document.body.classList.contains('body-gestion-dispo')) {
        initGestionDispoMedecinPage();
    }
    if (document.body.classList.contains('body-page-messages-medecin')) {
        initReponseEmailModal();
    }
    if (document.body.classList.contains('body-admin-send-email-specifique')) {
        initAdminSendSpecificEmailPage();
    }
});

function initMobileNav() {
    const navToggle = document.getElementById('mobile-nav-trigger');
    const mainNav = document.getElementById('main-nav');
    if (navToggle && mainNav) {
        navToggle.addEventListener('click', () => {
            const isExpanded = navToggle.getAttribute('aria-expanded') === 'true' || false;
            navToggle.setAttribute('aria-expanded', !isExpanded);
            mainNav.classList.toggle('active');
            navToggle.classList.toggle('is-active');
            document.body.classList.toggle('modal-open', !isExpanded);
        });
    }
    if (document.body.classList.contains('body-index')) {
        const header = document.querySelector('.site-header');
        if (header) {
            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) {
                    header.classList.add('header-scrolled');
                } else {
                    header.classList.remove('header-scrolled');
                }
            });
        }
    }
}

function initFooterYear() {
    const yearSpan = document.getElementById('footer-year');
    if (yearSpan) yearSpan.textContent = new Date().getFullYear();
}

function initDismissAlerts() {
    document.querySelectorAll('.alert .close-alert').forEach(button => {
        button.addEventListener('click', function() {
            this.parentElement.style.transition = 'opacity 0.3s ease, transform 0.3s ease, margin-top 0.3s ease';
            this.parentElement.style.opacity = '0';
            this.parentElement.style.transform = 'scale(0.95)';
            this.parentElement.style.marginTop = '-20px';
            setTimeout(() => {
                if(this.parentElement) this.parentElement.style.display = 'none';
            }, 300);
        });
    });
    document.querySelectorAll('.alert-success, .alert-info').forEach(alert => {
        if (!alert.classList.contains('no-auto-dismiss')) {
            setTimeout(() => {
                if (alert && alert.parentElement && alert.style.display !== 'none') {
                    const closeButton = alert.querySelector('.close-alert');
                    if (closeButton) closeButton.click();
                    else {
                        alert.style.transition = 'opacity 0.5s ease';
                        alert.style.opacity = '0';
                        setTimeout(() => { if(alert) alert.style.display = 'none'; }, 500);
                    }
                }
            }, 7000);
        }
    });
}

function openModal(modal) {
    if (modal == null) return;
    document.querySelectorAll('.modal').forEach(m => {
        if (m !== modal && m.style.display === 'block') closeModal(m);
    });
    modal.style.display = 'block';
    document.body.classList.add('modal-open');
    modal.setAttribute('aria-hidden', 'false');
    const focusableElements = modal.querySelectorAll('button, [href], input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusableElements.length > 0) {
        setTimeout(() => focusableElements[0].focus(), 50);
    }
}

function closeModal(modal) {
    if (modal == null) return;
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');
    modal.setAttribute('aria-hidden', 'true');
}

function initOpenModalFromUrl() {
    if (document.body.classList.contains('body-index') || document.querySelector('.modal')) {
        const urlParams = new URLSearchParams(window.location.search);
        const modalToOpenQueryParam = urlParams.get('open_modal');

        if (modalToOpenQueryParam) {
            const modalSelector = modalToOpenQueryParam.startsWith('#') ? modalToOpenQueryParam : `#${modalToOpenQueryParam}`;
            const modalElement = document.querySelector(decodeURIComponent(modalSelector));
            
            if (modalElement && modalElement.classList.contains('modal')) {
                openModal(modalElement);
                if (history.replaceState) {
                    const cleanUrl = window.location.pathname + window.location.hash;
                    history.replaceState(null, '', cleanUrl);
                }
            }
        }
    }
}

function initModals() {
    const openModalButtons = document.querySelectorAll('[data-modal-target]');
    const closeModalButtons = document.querySelectorAll('.close-modal-button, [data-close-modal]');
    const switchModalLinks = document.querySelectorAll('.switch-modal');

    openModalButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const modalId = button.dataset.modalTarget;
            const modal = document.querySelector(modalId);
            if (modal) {
                openModal(modal);
            }
        });
    });

    closeModalButtons.forEach(button => {
        button.addEventListener('click', () => {
            const modal = button.closest('.modal');
            closeModal(modal);
        });
    });

    switchModalLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const currentModal = link.closest('.modal');
            const targetModalId = link.dataset.targetModal;
            const targetModal = document.querySelector(targetModalId);
            
            if (currentModal) closeModal(currentModal);
            if (targetModal) openModal(targetModal);
        });
    });

    window.addEventListener('click', event => {
        if (event.target.classList.contains('modal')) {
            closeModal(event.target);
        }
    });

    window.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal[style*="display: block"]').forEach(modal => {
                closeModal(modal);
            });
        }
    });
}

function initAllFormValidations() {
    document.querySelectorAll('form.user-form').forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!validateGenericForm(this)) {
                event.preventDefault();
            }
        });
        const passwordField = form.querySelector('input[type="password"][name*="mot_de_passe"]:not([name*="ancien"])');
        const confirmPasswordField = form.querySelector('input[type="password"][name*="confirm"]');
        const feedbackElement = form.querySelector('.password-feedback');

        if (passwordField && confirmPasswordField && feedbackElement) {
            initPasswordConfirmationValidation(passwordField, confirmPasswordField, feedbackElement);
        }
    });
}

function validateGenericForm(form) {
    let isValid = true;
    form.querySelectorAll('input[required], select[required], textarea[required]').forEach(input => {
        const errorElement = input.closest('.form-group')?.querySelector('.form-error-message');
        if (input.type === 'checkbox' ? !input.checked : input.value.trim() === '') {
            isValid = false;
            input.classList.add('input-error');
            if (errorElement) errorElement.textContent = 'Ce champ est requis.';
        } else {
            input.classList.remove('input-error');
            if (errorElement) errorElement.textContent = '';
            if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
                isValid = false;
                input.classList.add('input-error');
                if (errorElement) errorElement.textContent = 'Format d\'email invalide.';
            }
            if (input.minLength > 0 && input.value.length < input.minLength) {
                isValid = false;
                input.classList.add('input-error');
                if (errorElement) errorElement.textContent = `Doit contenir au moins ${input.minLength} caractères.`;
            }
        }
    });
    return isValid;
}

function initPasswordConfirmationValidation(passwordField, confirmPasswordField, feedbackElement) {
    const validatePasswords = () => {
        if (passwordField.value === '' && confirmPasswordField.value === '') {
            if (!feedbackElement.classList.contains('error-message-display')) {
                 feedbackElement.textContent = '';
                 feedbackElement.className = 'form-field-feedback password-feedback';
            }
            confirmPasswordField.classList.remove('input-error', 'input-success');
            return;
        }

        if (passwordField.value !== confirmPasswordField.value) {
            feedbackElement.textContent = 'Les mots de passe ne correspondent pas.';
            feedbackElement.className = 'form-field-feedback password-feedback error-message-display';
            confirmPasswordField.classList.add('input-error');
            confirmPasswordField.classList.remove('input-success');
        } else if (passwordField.value.length < (passwordField.minLength || 8)) {
            feedbackElement.textContent = `Le mot de passe doit faire au moins ${passwordField.minLength || 8} caractères.`;
            feedbackElement.className = 'form-field-feedback password-feedback error-message-display';
            confirmPasswordField.classList.add('input-error');
            confirmPasswordField.classList.remove('input-success');
        }
        else {
            feedbackElement.textContent = 'Les mots de passe correspondent.';
            feedbackElement.className = 'form-field-feedback password-feedback success-message-display';
            confirmPasswordField.classList.remove('input-error');
            confirmPasswordField.classList.add('input-success');
        }
    };

    passwordField.addEventListener('input', validatePasswords);
    confirmPasswordField.addEventListener('input', validatePasswords);
    if (passwordField.value || confirmPasswordField.value) {
        validatePasswords();
    }
}

function escapeHtml(unsafe) {
    if (unsafe === null || typeof unsafe === 'undefined') return '';
    if (typeof unsafe !== 'string') {
        try {
            unsafe = String(unsafe);
        } catch (e) {
            return '[valeur non affichable]';
        }
    }
    return unsafe
         .replace(/&/g, "&")
         .replace(/</g, "<")
         .replace(/>/g, ">")
         .replace(/"/g, "\"")
         .replace(/'/g, "'");
}

function initIndexPage() {
    loadTestimonials();
    initMiniMapsForContainer(document.getElementById('apercuDoctorListContainer'));
    initSpecialtyTooltips(); 
    initDoctorHorairesToggle(document.getElementById('apercuDoctorListContainer'));
}

function initSpecialtyTooltips() {
    const specialtySelect = document.getElementById('specialtySelect');
    const tooltip = document.getElementById('specialty-description-tooltip');
    if (!specialtySelect || !tooltip) return;

    const updateTooltip = () => {
        const selectedOption = specialtySelect.options[specialtySelect.selectedIndex];
        const description = selectedOption ? selectedOption.dataset.description : '';
        if (description && specialtySelect.value !== "") {
            tooltip.innerHTML = escapeHtml(description);
            tooltip.style.display = 'block';
            const selectRect = specialtySelect.getBoundingClientRect();
            const searchContainerRect = specialtySelect.closest('.search-container').getBoundingClientRect();
            tooltip.style.top = (selectRect.bottom - searchContainerRect.top + 8) + 'px';
            tooltip.style.left = (selectRect.left - searchContainerRect.left) + 'px';
            tooltip.style.width = selectRect.width + 'px';
            tooltip.style.maxWidth = '400px';
        } else {
            tooltip.style.display = 'none';
        }
    };
    specialtySelect.addEventListener('change', updateTooltip);
    specialtySelect.addEventListener('focus', updateTooltip);
    specialtySelect.addEventListener('blur', () => {
        setTimeout(() => {
            if (specialtySelect.value === "" || document.activeElement !== specialtySelect) {
                tooltip.style.display = 'none';
            }
        }, 150); 
    });
    document.addEventListener('click', function(event) {
        if (specialtySelect && tooltip) {
            const isClickInsideSelect = specialtySelect.contains(event.target);
            const isClickInsideTooltip = tooltip.contains(event.target);
            if (!isClickInsideSelect && !isClickInsideTooltip) {
                tooltip.style.display = 'none';
            }
        }
    });
}

async function loadTestimonials() {
    const testimonialSlider = document.getElementById('testimonialSlider');
    const loadingP = document.getElementById('loading-testimonials');
    const noResultsP = document.getElementById('no-testimonials-found');
    if (!testimonialSlider || !loadingP || !noResultsP) return;

    loadingP.style.display = 'block';
    noResultsP.style.display = 'none';
    while (testimonialSlider.firstChild) {
        testimonialSlider.removeChild(testimonialSlider.firstChild);
    }
    testimonialSlider.appendChild(loadingP);
    testimonialSlider.appendChild(noResultsP);

    try {
        const response = await fetch('php/api_temoignages.php');
        if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);
        const testimonialsData = await response.json();
        loadingP.style.display = 'none';
        if (testimonialsData.error) {
            noResultsP.textContent = testimonialsData.error;
            noResultsP.style.display = 'block';
            return;
        }
        if (testimonialsData && testimonialsData.length > 0) {
            testimonialsData.forEach(testimonial => {
                const card = document.createElement('div');
                card.className = 'testimonial-card';
                card.innerHTML = `
                    <p class="testimonial-text">"${escapeHtml(testimonial.contenu)}"</p>
                    <p class="testimonial-author">- ${escapeHtml(testimonial.nom)}</p>
                `;
                testimonialSlider.appendChild(card);
            });
        } else {
            noResultsP.style.display = 'block';
        }
    } catch (error) {
        loadingP.style.display = 'none';
        noResultsP.textContent = 'Erreur lors du chargement des témoignages. Veuillez réessayer.';
        noResultsP.style.display = 'block';
    }
}

let currentFiltersDoctors = { nom_medecin: '', specialite: '', adresse: '', page: 1 };
let leafletMapInstanceLarge = null;
let currentMapMarkerLarge = null;
let activeMiniMaps = {};
const TILE_LAYER_URL_SATELLITE = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
const TILE_LAYER_ATTRIBUTION_SATELLITE = 'Tiles © Esri — Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community';

function initDoctorsPage() {
    const filterForm = document.getElementById('doctorsPageFilterForm');
    const safeInitialFilters = typeof initialFiltersFromPHP !== 'undefined' ? initialFiltersFromPHP : { specialite: '', nom_medecin: '', adresse: '', page: 1 };
    currentFiltersDoctors = { ...safeInitialFilters };
    const searchInput = document.getElementById('searchDoctorNamePage');
    const specialtySelect = document.getElementById('filterSpecialtyPage');
    const locationInput = document.getElementById('filterLocationPage');
    if (searchInput && currentFiltersDoctors.nom_medecin) searchInput.value = currentFiltersDoctors.nom_medecin;
    if (specialtySelect && currentFiltersDoctors.specialite) specialtySelect.value = currentFiltersDoctors.specialite;
    if (locationInput && currentFiltersDoctors.adresse) locationInput.value = currentFiltersDoctors.adresse;
    loadDoctors(currentFiltersDoctors.page, currentFiltersDoctors);
    if (filterForm) {
        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            currentFiltersDoctors.page = 1;
            currentFiltersDoctors.nom_medecin = searchInput ? searchInput.value : '';
            currentFiltersDoctors.specialite = specialtySelect ? specialtySelect.value : '';
            currentFiltersDoctors.adresse = locationInput ? locationInput.value : '';
            loadDoctors(currentFiltersDoctors.page, currentFiltersDoctors);
            updatePageTitleDoctors(currentFiltersDoctors.specialite, currentFiltersDoctors.nom_medecin, currentFiltersDoctors.adresse);
            toggleResetButtonVisibilityDoctors(currentFiltersDoctors);
        });
    }
    const resetButton = document.getElementById('resetFiltersButton');
    if (resetButton) {
        resetButton.addEventListener('click', (e) => {
            e.preventDefault();
            currentFiltersDoctors = { nom_medecin: '', specialite: '', adresse: '', page: 1 };
            if(filterForm) filterForm.reset();
            loadDoctors(currentFiltersDoctors.page, currentFiltersDoctors);
            updatePageTitleDoctors('', '', '');
            toggleResetButtonVisibilityDoctors(currentFiltersDoctors);
        });
    }
    toggleResetButtonVisibilityDoctors(currentFiltersDoctors);
}

function updatePageTitleDoctors(specialite, nom_medecin, adresse) {
    const titleElement = document.getElementById('doctorsPageTitle');
    if (!titleElement) return;
    let titre_parts = [];
    if (specialite) {
        let specText = specialite.charAt(0).toUpperCase() + specialite.slice(1);
        if (specialite.toLowerCase() === 'autre') {
            specText = "Autres Professionnels";
        } else if (specialite.toLowerCase().endsWith('ue')) {
            specText += 's';
        } else {
            specText += 's';
        }
        titre_parts.push(specText);
    }
    if (nom_medecin) titre_parts.push(`recherche "${escapeHtml(nom_medecin)}"`);
    if (adresse) titre_parts.push(`près de "${escapeHtml(adresse)}"`);
    if (titre_parts.length > 0) {
        titleElement.textContent = titre_parts.join(', ');
    } else {
        titleElement.textContent = 'Tous Nos Professionnels de Santé';
    }
}

function toggleResetButtonVisibilityDoctors(filters) {
    const resetButton = document.getElementById('resetFiltersButton');
    if (!resetButton) return;
    if (filters.nom_medecin || filters.specialite || filters.adresse ) {
        resetButton.style.display = 'inline-block';
    } else {
        resetButton.style.display = 'none';
    }
}

function nl2br(str) {
    if (typeof str === 'undefined' || str === null) {
        return '';
    }
    return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
}

async function loadDoctors(page, filters) {
    const container = document.getElementById('doctorListContainer');
    const loadingP = document.getElementById('loading-doctors');
    const noResultsP = document.getElementById('no-doctors-found');
    const paginationControls = document.getElementById('doctorsPaginationControls');
    if (!container || !loadingP || !noResultsP || !paginationControls) return;

    loadingP.style.display = 'block';
    noResultsP.style.display = 'none';
    container.innerHTML = '';
    container.appendChild(loadingP);
    container.appendChild(noResultsP);
    Object.values(activeMiniMaps).forEach(mapInstance => {
        if (mapInstance) mapInstance.remove();
    });
    activeMiniMaps = {};
    paginationControls.style.display = 'none';

    let apiUrl = `../php/api_medecins.php?page=${page}&limit=6`;
    if (filters.nom_medecin) apiUrl += `&nom=${encodeURIComponent(filters.nom_medecin)}`;
    if (filters.specialite) apiUrl += `&specialite=${encodeURIComponent(filters.specialite)}`;
    if (filters.adresse) apiUrl += `&adresse=${encodeURIComponent(filters.adresse)}`;

    try {
        const response = await fetch(apiUrl);
        if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);
        const data = await response.json();
        loadingP.style.display = 'none';
        if (data.medecins && data.medecins.length > 0) {
            data.medecins.forEach(medecin => {
                const card = document.createElement('div');
                card.className = 'doctor-card';
                card.dataset.medecinId = medecin.id;
                const photoUrl = medecin.photo ? `../${escapeHtml(medecin.photo)}` : '../assets/images/placeholder-medecin.jpg';
                let mapContainerHtml = '';
                const mapId = `map-medecin-api-${medecin.id}`;
                const lat = parseFloat(medecin.latitude);
                const lon = parseFloat(medecin.longitude);
                if (!isNaN(lat) && !isNaN(lon)) {
                    mapContainerHtml = `
                        <div id="${mapId}" class="doctor-card-map-container"
                             data-latitude="${lat}" data-longitude="${lon}"
                             data-medecin-nom="Dr. ${escapeHtml(medecin.prenom)} ${escapeHtml(medecin.nom)}"
                             title="Cliquez pour agrandir la carte">
                             <span class="map-overlay-text">Agrandir</span>
                        </div>`;
                } else {
                     mapContainerHtml = `<p class="text-muted text-center" style="font-size:0.85em; padding: var(--spacing-md) 0;">Localisation non disponible.</p>`;
                }
                const horairesCompletsHtml = medecin.horaires ? nl2br(escapeHtml(medecin.horaires)) : '<p><i>Horaires non spécifiés.</i></p>';
                const horairesToggleHtml = `
                    <div class="doctor-horaires-toggle-container">
                        <button type="button" class="btn btn-xs btn-outline-primary doctor-horaires-toggle-btn">
                            <i class="far fa-clock icon-left"></i>Voir les Horaires
                        </button>
                        <div class="doctor-horaires-details" style="display: none;">
                            ${horairesCompletsHtml}
                        </div>
                    </div>`;

                card.innerHTML = `
                    <div class="doctor-card-image-wrapper">
                        <img src="${photoUrl}" alt="Dr. ${escapeHtml(medecin.prenom)} ${escapeHtml(medecin.nom)}" class="doctor-card-image">
                    </div>
                    <div class="doctor-card-content">
                        <h3 class="doctor-name">Dr. ${escapeHtml(medecin.prenom)} ${escapeHtml(medecin.nom)}</h3>
                        <p class="doctor-specialty">${escapeHtml(medecin.specialite)}</p>
                        ${mapContainerHtml}
                        ${horairesToggleHtml}
                        ${medecin.telephone ? `<p class="doctor-address" style="margin-top: var(--spacing-sm);"><i class="fas fa-phone-alt icon-left"></i><a href="tel:${escapeHtml(medecin.telephone)}">${escapeHtml(medecin.telephone)}</a></p>` : ''}
                         <p class="doctor-address">
                            <i class="fas fa-map-marker-alt icon-left"></i>
                            ${medecin.adresse ? escapeHtml(medecin.adresse) : 'Adresse non spécifiée'}
                        </p>
                        <div class="doctor-card-actions">
                           <a href="rendez-vous.php?medecin_id=${medecin.id}&medecin_nom=${encodeURIComponent('Dr. ' + escapeHtml(medecin.prenom) + ' ' + escapeHtml(medecin.nom))}" class="btn btn-sm btn-primary doctor-profile-link">
                                <i class="fas fa-calendar-check"></i> Prendre RDV
                            </a>
                        </div>
                    </div>
                `;
                container.appendChild(card);
                if (!isNaN(lat) && !isNaN(lon) && typeof L !== 'undefined') {
                    initMiniMap(mapId, lat, lon, `Dr. ${escapeHtml(medecin.prenom)} ${escapeHtml(medecin.nom)}`);
                }
            });
            initDoctorHorairesToggle(container);
            setupDoctorsPagination(data.pagination);
        } else {
            noResultsP.style.display = 'block';
        }
    } catch (error) {
        loadingP.style.display = 'none';
        noResultsP.textContent = 'Erreur lors du chargement des données. Veuillez réessayer.';
        noResultsP.style.display = 'block';
    }
}

function initDoctorHorairesToggle(containerElement) {
    const toggleButtons = containerElement.querySelectorAll('.doctor-horaires-toggle-btn');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const detailsDiv = this.nextElementSibling;
            if (detailsDiv) {
                if (detailsDiv.style.display === 'none' || detailsDiv.style.display === '') {
                    detailsDiv.style.display = 'block';
                    this.innerHTML = '<i class="far fa-eye-slash icon-left"></i>Cacher les Horaires';
                } else {
                    detailsDiv.style.display = 'none';
                    this.innerHTML = '<i class="far fa-clock icon-left"></i>Voir les Horaires';
                }
            }
        });
    });
}

function initMiniMap(mapId, lat, lon, medecinNom) {
    setTimeout(() => {
        const mapElement = document.getElementById(mapId);
        if (mapElement && !mapElement._leaflet_id) {
             const miniMap = L.map(mapId, {
                center: [lat, lon], zoom: 14, zoomControl: false,
                scrollWheelZoom: false, dragging: false, doubleClickZoom: false,
                attributionControl: false
            });
            L.tileLayer(TILE_LAYER_URL_SATELLITE, { attribution: TILE_LAYER_ATTRIBUTION_SATELLITE, maxZoom: 19 }).addTo(miniMap);
            L.marker([lat, lon]).addTo(miniMap);
            activeMiniMaps[mapId] = miniMap;
            mapElement.addEventListener('click', () => {
                openLargeMap(lat, lon, medecinNom);
            });
        }
    }, 50);
}

function initMiniMapsForContainer(containerElement) {
    if (!containerElement || typeof L === 'undefined') return;
    const mapDivs = containerElement.querySelectorAll('.doctor-card-map-container');
    mapDivs.forEach(mapDiv => {
        const lat = parseFloat(mapDiv.dataset.latitude);
        const lon = parseFloat(mapDiv.dataset.longitude);
        const medNom = mapDiv.dataset.medecinNom;
        const mapId = mapDiv.id;
        if (!isNaN(lat) && !isNaN(lon) && !activeMiniMaps[mapId] && !mapDiv._leaflet_id) {
            setTimeout(() => {
                 const miniMap = L.map(mapId, {
                    center: [lat, lon], zoom: 14, zoomControl: false,
                    scrollWheelZoom: false, dragging: false, doubleClickZoom: false,
                    attributionControl: false
                });
                L.tileLayer(TILE_LAYER_URL_SATELLITE, { attribution: TILE_LAYER_ATTRIBUTION_SATELLITE, maxZoom: 19 }).addTo(miniMap);
                L.marker([lat, lon]).addTo(miniMap);
                activeMiniMaps[mapId] = miniMap;
                mapDiv.addEventListener('click', () => {
                    openLargeMap(lat, lon, medNom);
                });
            }, 50);
        }
    });
}

function setupDoctorsPagination(paginationData) {
    const paginationControls = document.getElementById('doctorsPaginationControls');
    const paginationInfo = document.getElementById('paginationInfoDoctors');
    const paginationNav = document.getElementById('paginationNavDoctors');
    if (!paginationControls || !paginationInfo || !paginationNav) return;
    if (!paginationData || paginationData.totalPages <= 1) {
        paginationControls.style.display = 'none';
        return;
    }
    paginationInfo.textContent = `Page ${paginationData.currentPage} sur ${paginationData.totalPages} (Total: ${paginationData.totalItems} médecins)`;
    paginationNav.innerHTML = '';
    if (paginationData.currentPage > 1) {
        paginationNav.appendChild(createPaginationButton('« Préc.', paginationData.currentPage - 1));
    } else {
        const prevDisabled = document.createElement('span');
        prevDisabled.className = 'page-link disabled';
        prevDisabled.textContent = '« Préc.';
        paginationNav.appendChild(prevDisabled);
    }
    const numLinksToShow = 2;
    let startPage = Math.max(1, paginationData.currentPage - numLinksToShow);
    let endPage = Math.min(paginationData.totalPages, paginationData.currentPage + numLinksToShow);
    if (startPage > 1) {
        paginationNav.appendChild(createPaginationButton('1', 1));
        if (startPage > 2) {
            const ellipsis = document.createElement('span'); ellipsis.className = 'ellipsis'; ellipsis.textContent = '…';
            paginationNav.appendChild(ellipsis);
        }
    }
    for (let i = startPage; i <= endPage; i++) {
        paginationNav.appendChild(createPaginationButton(i, i, paginationData.currentPage === i));
    }
    if (endPage < paginationData.totalPages) {
        if (endPage < paginationData.totalPages - 1) {
            const ellipsis = document.createElement('span'); ellipsis.className = 'ellipsis'; ellipsis.textContent = '…';
            paginationNav.appendChild(ellipsis);
        }
        paginationNav.appendChild(createPaginationButton(paginationData.totalPages, paginationData.totalPages));
    }
    if (paginationData.currentPage < paginationData.totalPages) {
        paginationNav.appendChild(createPaginationButton('Suiv. »', paginationData.currentPage + 1));
    } else {
        const nextDisabled = document.createElement('span');
        nextDisabled.className = 'page-link disabled';
        nextDisabled.textContent = 'Suiv. »';
        paginationNav.appendChild(nextDisabled);
    }
    paginationControls.style.display = 'flex';
}

function createPaginationButton(text, page, isActive = false) {
    const button = document.createElement('a');
    button.href = '#';
    button.className = 'page-link';
    if (isActive) button.classList.add('active');
    button.textContent = text;
    button.dataset.page = page;
    button.addEventListener('click', (e) => {
        e.preventDefault();
        currentFiltersDoctors.page = page;
        loadDoctors(currentFiltersDoctors.page, currentFiltersDoctors);
        const filterToolbar = document.getElementById('doctorsPageFilterToolbar');
        if (filterToolbar) {
             window.scrollTo({ top: filterToolbar.offsetTop - (document.querySelector('.site-header')?.offsetHeight || 80), behavior: 'smooth' });
        }
    });
    return button;
}

function openLargeMap(lat, lon, medecinNom) {
    const mapModalLarge = document.getElementById('map-modal-large');
    const mapModalTitleLarge = document.getElementById('map-modal-large-title-text');
    const mapContainerModalLarge = document.getElementById('map-container-modal-large');
    if (!mapModalLarge || !mapModalTitleLarge || !mapContainerModalLarge) return;
    mapModalTitleLarge.textContent = `Localisation détaillée de ${escapeHtml(medecinNom)}`;
    if (leafletMapInstanceLarge) {
        leafletMapInstanceLarge.remove();
        leafletMapInstanceLarge = null;
    }
    mapContainerModalLarge.innerHTML = '';
    if (typeof L !== 'undefined') {
        leafletMapInstanceLarge = L.map(mapContainerModalLarge).setView([lat, lon], 16);
        L.tileLayer(TILE_LAYER_URL_SATELLITE, { attribution: TILE_LAYER_ATTRIBUTION_SATELLITE, maxZoom: 19 }).addTo(leafletMapInstanceLarge);
        const googleMapsLink = `https://www.google.com/maps/search/?api=1&query=${lat},${lon}`;
        currentMapMarkerLarge = L.marker([lat, lon]).addTo(leafletMapInstanceLarge)
            .bindPopup(`<b>${escapeHtml(medecinNom)}</b><br>Cabinet médical.<br><a href="${googleMapsLink}" target="_blank" rel="noopener noreferrer" style="color:var(--color-brand-primary);font-weight:bold;">Itinéraire via Google Maps</a>`)
            .openPopup();
    } else {
        mapContainerModalLarge.innerHTML = "<p class='text-center text-danger'>Erreur: Librairie de carte non chargée.</p>";
    }
    openModal(mapModalLarge);
    setTimeout(() => {
        if (leafletMapInstanceLarge) leafletMapInstanceLarge.invalidateSize();
    }, 150); 
}

function initRdvPage() {
    const medecinSelect = document.getElementById('medecin_id_rdv');
    const dateInput = document.getElementById('date_rdv');
    const timeSlotsContainer = document.getElementById('time-slots-container');
    const timeSlotsGrid = document.getElementById('time-slots-grid');
    const timeSlotsLabel = document.getElementById('time-slots-label');
    const loadingCreneauxP = document.getElementById('loading-creneaux');
    const noSlotsMessageP = document.getElementById('no-slots-message');
    const heureRdvHiddenInput = document.getElementById('heure_rdv_hidden');
    const medecinNomHiddenInput = document.getElementById('medecin_nom_rdv_hidden');
    const submitRdvButton = document.getElementById('submitRdvButton');

    function toggleSubmitButton() {
        if (medecinSelect && dateInput && heureRdvHiddenInput && submitRdvButton) {
            submitRdvButton.disabled = !(medecinSelect.value && dateInput.value && heureRdvHiddenInput.value);
        }
    }
    function updateMedecinNomHidden() {
        if (medecinSelect && medecinNomHiddenInput) {
            medecinNomHiddenInput.value = medecinSelect.value ? medecinSelect.options[medecinSelect.selectedIndex].dataset.nom || '' : '';
        }
    }
    async function fetchAndDisplayTimeSlots() {
        if (!medecinSelect || !dateInput || !timeSlotsContainer || !timeSlotsGrid || !timeSlotsLabel || !loadingCreneauxP || !noSlotsMessageP || !heureRdvHiddenInput) return;
        if (!medecinSelect.value || !dateInput.value) {
            timeSlotsContainer.style.display = 'none';
            timeSlotsLabel.style.display = 'none';
            heureRdvHiddenInput.value = '';
            toggleSubmitButton();
            return;
        }
        timeSlotsGrid.innerHTML = '';
        loadingCreneauxP.style.display = 'block';
        noSlotsMessageP.style.display = 'none';
        timeSlotsContainer.style.display = 'block';
        timeSlotsLabel.style.display = 'block';
        heureRdvHiddenInput.value = '';
        toggleSubmitButton();
        try {
            const response = await fetch(`../php/api_creneaux_disponibles.php?medecin_id=${medecinSelect.value}&date=${dateInput.value}`);
            if (!response.ok) throw new Error('Erreur réseau ou serveur.');
            const creneaux = await response.json();
            loadingCreneauxP.style.display = 'none';
            if (creneaux.error) {
                noSlotsMessageP.textContent = creneaux.error;
                noSlotsMessageP.style.display = 'block';
            } else if (creneaux.length > 0) {
                creneaux.forEach(heure => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'time-slot-button';
                    button.textContent = heure;
                    button.dataset.heure = heure + ":00";
                    button.addEventListener('click', function() {
                        document.querySelectorAll('.time-slot-button.selected').forEach(btn => btn.classList.remove('selected'));
                        this.classList.add('selected');
                        heureRdvHiddenInput.value = this.dataset.heure;
                        const errorMsgEl = document.getElementById('heure_rdv_error_msg');
                        if(errorMsgEl) errorMsgEl.style.display = 'none';
                        toggleSubmitButton();
                    });
                    timeSlotsGrid.appendChild(button);
                });
            } else {
                noSlotsMessageP.textContent = "Aucun créneau disponible pour cette date. Veuillez essayer une autre date ou un autre médecin.";
                noSlotsMessageP.style.display = 'block';
            }
        } catch (error) {
            loadingCreneauxP.style.display = 'none';
            noSlotsMessageP.textContent = "Une erreur s'est produite lors de la récupération des créneaux. Veuillez réessayer.";
            noSlotsMessageP.style.display = 'block';
        }
    }
    if (medecinSelect && dateInput) {
        medecinSelect.addEventListener('change', () => {
            dateInput.disabled = !medecinSelect.value;
            updateMedecinNomHidden();
            fetchAndDisplayTimeSlots();
        });
        dateInput.addEventListener('change', fetchAndDisplayTimeSlots);
        if (typeof rdvPageInitialData !== 'undefined') {
            if (rdvPageInitialData.medecinId) {
                medecinSelect.value = rdvPageInitialData.medecinId;
                dateInput.disabled = false;
                 if (medecinNomHiddenInput && rdvPageInitialData.medecinNom) {
                    medecinNomHiddenInput.value = rdvPageInitialData.medecinNom;
                }
            }
            if (rdvPageInitialData.date) {
                dateInput.value = rdvPageInitialData.date;
            }
            if (medecinSelect.value && dateInput.value) {
                fetchAndDisplayTimeSlots().then(() => {
                    if (heureRdvHiddenInput && rdvPageInitialData.heure) {
                        const heureSimple = rdvPageInitialData.heure.substring(0, 5);
                        const matchingButton = timeSlotsGrid.querySelector(`button[data-heure^="${heureSimple}"]`);
                        if (matchingButton) matchingButton.click();
                        else heureRdvHiddenInput.value = '';
                    }
                    toggleSubmitButton();
                });
            }
        }
        toggleSubmitButton();
    }
}

function initFaqAccordion() {
    const faqItems = document.querySelectorAll('.faq-item .faq-question');
    faqItems.forEach(item => {
        item.addEventListener('click', () => {
            const answer = item.nextElementSibling;
            const icon = item.querySelector('.icon i');
            const parentItem = item.parentElement;
            const isOpen = parentItem.classList.contains('active');
            if (isOpen) {
                answer.style.maxHeight = null;
                answer.style.paddingTop = '0';
                answer.style.paddingBottom = '0';
                icon.className = 'fas fa-plus';
                parentItem.classList.remove('active');
            } else {
                answer.style.maxHeight = answer.scrollHeight + "px";
                answer.style.paddingTop = '15px';
                answer.style.paddingBottom = '15px';
                icon.className = 'fas fa-minus';
                parentItem.classList.add('active');
            }
        });
    });
}

function initMesRdvPatientPageModals() {
    const annulationModalElPatient = document.getElementById('annulationRdvModalPatient');
    const rdvIdInputElPatient = document.getElementById('rdvIdAnnulationInputPatient');
    const motifTextareaElPatient = document.getElementById('motifAnnulationTextareaPatient');
    const wordCountDisplayElPatient = document.getElementById('wordCountMotifPatient');
    const errorMotifDisplayElPatient = document.getElementById('error-motifAnnulationPatient');
    const motifInfoModalElPatient = document.getElementById('motifAnnulationInfoModalPatient');
    const motifInfoContentElPatient = document.getElementById('motifInfoContentPatient');
    const rdvTableBody = document.querySelector('.rdv-table tbody');
    if (rdvTableBody) {
        rdvTableBody.addEventListener('click', function(event) {
            const targetButton = event.target.closest('button');
            if (!targetButton) return;
            if (targetButton.classList.contains('open-annulation-modal-patient')) {
                const rdvId = targetButton.dataset.rdvId;
                if (rdvIdInputElPatient) rdvIdInputElPatient.value = rdvId;
                if (motifTextareaElPatient) motifTextareaElPatient.value = '';
                updateWordCountPatientVisuals();
                if (annulationModalElPatient) openModal(annulationModalElPatient);
            } else if (targetButton.classList.contains('open-motif-info-modal-patient')) {
                const rdvId = targetButton.dataset.rdvId;
                if (typeof motifsAnnulationGlobauxMesRdvPage !== 'undefined' && motifsAnnulationGlobauxMesRdvPage[rdvId] && motifInfoModalElPatient && motifInfoContentElPatient) {
                    motifInfoContentElPatient.textContent = motifsAnnulationGlobauxMesRdvPage[rdvId];
                    openModal(motifInfoModalElPatient);
                } else if (motifInfoModalElPatient && motifInfoContentElPatient) {
                    motifInfoContentElPatient.textContent = "Aucun motif spécifique n'a été fourni.";
                    openModal(motifInfoModalElPatient);
                }
            }
        });
    }
    function updateWordCountPatientVisuals() {
        if (!motifTextareaElPatient || !wordCountDisplayElPatient || !errorMotifDisplayElPatient) return;
        const text = motifTextareaElPatient.value.trim();
        const words = text === '' ? 0 : text.split(/\s+/).filter(word => word.length > 0).length;
        wordCountDisplayElPatient.textContent = `${words} mot(s). Minimum 10 mots requis.`;
        if (words < 10) {
            wordCountDisplayElPatient.style.color = 'var(--color-danger)';
            errorMotifDisplayElPatient.textContent = 'Le motif doit contenir au moins 10 mots.';
            errorMotifDisplayElPatient.style.display = 'block';
        } else {
            wordCountDisplayElPatient.style.color = 'var(--text-color-muted)';
            errorMotifDisplayElPatient.textContent = '';
            errorMotifDisplayElPatient.style.display = 'none';
        }
    }
    if (motifTextareaElPatient) {
        motifTextareaElPatient.addEventListener('input', updateWordCountPatientVisuals);
        if (annulationModalElPatient && annulationModalElPatient.style.display === 'block') {
            updateWordCountPatientVisuals();
        }
    }
    const formAnnulationElPatient = document.getElementById('formAnnulationRdvPatient');
    if(formAnnulationElPatient) {
        formAnnulationElPatient.addEventListener('submit', function(event) {
            const motif = motifTextareaElPatient.value.trim();
            const nbMots = motif === '' ? 0 : motif.split(/\s+/).filter(word => word.length > 0).length;
            if (nbMots < 10) {
                event.preventDefault();
                if(typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Motif insuffisant', text: 'Votre justification doit contenir au moins 10 mots.', confirmButtonColor: 'var(--color-danger)' });
                if (errorMotifDisplayElPatient) { errorMotifDisplayElPatient.textContent = 'Le motif doit contenir au moins 10 mots.'; errorMotifDisplayElPatient.style.display = 'block'; }
                return; 
            }
            if (errorMotifDisplayElPatient) { errorMotifDisplayElPatient.textContent = ''; errorMotifDisplayElPatient.style.display = 'none';}
            event.preventDefault();
            if(typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Confirmer l\'annulation ?', text: 'Êtes-vous sûr(e) de vouloir annuler ce rendez-vous ?', icon: 'warning',
                    showCancelButton: true, confirmButtonColor: 'var(--color-danger)', cancelButtonColor: 'var(--color-brand-blue)',
                    confirmButtonText: 'Oui, annuler', cancelButtonText: 'Non'
                }).then((result) => {
                    if (result.isConfirmed) { 
                        const returnUrlInput = formAnnulationElPatient.querySelector('input[name="return_url"]');
                        if (!returnUrlInput) {
                             const newReturnUrlInput = document.createElement('input');
                             newReturnUrlInput.type = 'hidden'; newReturnUrlInput.name = 'return_url';
                             newReturnUrlInput.value = window.location.pathname + window.location.search;
                             formAnnulationElPatient.appendChild(newReturnUrlInput);
                        } else {
                            returnUrlInput.value = window.location.pathname + window.location.search;
                        }
                        formAnnulationElPatient.submit(); 
                    }
                });
            } else {
                if(confirm('Êtes-vous sûr(e) de vouloir annuler ce rendez-vous ?')) {
                    const returnUrlInput = formAnnulationElPatient.querySelector('input[name="return_url"]');
                     if (!returnUrlInput) {
                         const newReturnUrlInput = document.createElement('input');
                         newReturnUrlInput.type = 'hidden'; newReturnUrlInput.name = 'return_url';
                         newReturnUrlInput.value = window.location.pathname + window.location.search;
                         formAnnulationElPatient.appendChild(newReturnUrlInput);
                     } else {
                         returnUrlInput.value = window.location.pathname + window.location.search;
                     }
                    formAnnulationElPatient.submit();
                }
            }
        });
    }
    const urlParamsRdvPatient = new URLSearchParams(window.location.search);
    const errorMotifRdvId = urlParamsRdvPatient.get('error_motif_rdv');
    if (errorMotifRdvId && annulationModalElPatient) {
        const annulationButtonForError = document.querySelector(`.open-annulation-modal-patient[data-rdv-id="${errorMotifRdvId}"]`);
        if (annulationButtonForError) {
             if (rdvIdInputElPatient) rdvIdInputElPatient.value = errorMotifRdvId;
             openModal(annulationModalElPatient);
             if (motifTextareaElPatient) {
                motifTextareaElPatient.focus();
                updateWordCountPatientVisuals();
             }
        }
    }
}

function confirmActionRdvMedecin(rdvId, action, message, requiresMotif = false) {
    if (typeof Swal === 'undefined') {
        if (confirm(message)) {
            let url = `gerer_demande_rdv.php?id=${rdvId}&action=${action}`;
            if (requiresMotif && (action === 'refuser' || action === 'annuler')) {
                const motif = prompt("Veuillez entrer un motif pour cette action :");
                if (motif !== null) {
                    if (action === 'annuler' && (!motif || motif.trim().split(/\s+/).filter(Boolean).length < 10)) {
                        alert("Pour une annulation, un motif d'au moins 10 mots est requis.");
                        return;
                    }
                    url += `&motif=${encodeURIComponent(motif)}`;
                } else if (action === 'annuler') {
                    return;
                }
            }
            url += `&return_url=${encodeURIComponent(window.location.href)}`;
            window.location.href = url;
        }
        return;
    }
    const swalConfig = {
        title: message, icon: 'warning', showCancelButton: true,
        confirmButtonColor: (action === 'accepter') ? 'var(--color-success)' : 'var(--color-danger)',
        cancelButtonColor: 'var(--color-brand-blue)',
        confirmButtonText: `Oui, ${action.charAt(0).toUpperCase() + action.slice(1)}`,
        cancelButtonText: 'Non, annuler',
    };
    if (requiresMotif && (action === 'refuser' || action === 'annuler')) {
        swalConfig.input = 'textarea';
        swalConfig.inputPlaceholder = 'Motif du refus/de l\'annulation (recommandé si refus, obligatoire si annulation)...';
        swalConfig.inputAttributes = { 'aria-label': 'Motif du refus ou de l\'annulation' };
        swalConfig.inputValidator = (value) => {
            if (action === 'annuler' && (!value || value.trim().split(/\s+/).filter(Boolean).length < 10)) {
                return 'Pour une annulation, un motif d\'au moins 10 mots est requis.';
            }
            return null;
        };
    }
    Swal.fire(swalConfig).then((result) => {
        if (result.isConfirmed) {
            let url = `gerer_demande_rdv.php?id=${rdvId}&action=${action}`;
            if (action === 'annuler') {
                url = `annuler_rdv.php?id=${rdvId}`;
            }
            if (result.value && (action === 'refuser' || action === 'annuler')) {
                url += `&motif=${encodeURIComponent(result.value)}`;
            }
            url += `&return_url=${encodeURIComponent(window.location.href)}`;
            window.location.href = url;
        }
    });
}

function initMesRdvMedecinPageModals() {
    const motifInfoModalMedecin = document.getElementById('motifRdvInfoModalMedecin');
    const motifInfoContentMedecin = document.getElementById('motifInfoContentMedecin');
    const refusModalElMedecin = document.getElementById('refusRdvMedecinModal');
    const rdvIdInputRefusElMedecin = document.getElementById('rdvIdRefusInputMedecin');
    const annulationModalElMedecin = document.getElementById('annulationRdvMedecinModal');
    const rdvIdInputAnnulElMedecin = document.getElementById('rdvIdAnnulationInputMedecin');
    const motifTextareaAnnulElMedecin = document.getElementById('motifAnnulationTextareaMedecin');
    const wordCountDisplayAnnulMedecin = document.getElementById('wordCountMotifMedecin');
    const errorMotifDisplayAnnulMedecin = document.getElementById('error-motifAnnulationMedecin');
    const rdvTableBodyMed = document.querySelector('.rdv-table tbody');
    if (rdvTableBodyMed) {
        rdvTableBodyMed.addEventListener('click', function(event) {
            const targetButton = event.target.closest('button[data-modal-target]');
            if (!targetButton) return;
            const modalTargetId = targetButton.dataset.modalTarget;
            const rdvId = targetButton.dataset.rdvId;
            if (modalTargetId === '#motifRdvInfoModalMedecin') {
                if (typeof motifsGlobauxRdvMedecinPageList !== 'undefined' && motifsGlobauxRdvMedecinPageList[rdvId] && motifInfoModalMedecin && motifInfoContentMedecin) {
                    motifInfoContentMedecin.textContent = motifsGlobauxRdvMedecinPageList[rdvId];
                    openModal(motifInfoModalMedecin);
                } else if (motifInfoModalMedecin && motifInfoContentMedecin) {
                    motifInfoContentMedecin.textContent = "Aucun motif spécifique n'a été fourni.";
                    openModal(motifInfoModalMedecin);
                }
            } else if (modalTargetId === '#refusRdvMedecinModal') {
                if (rdvIdInputRefusElMedecin) rdvIdInputRefusElMedecin.value = rdvId;
                if (refusModalElMedecin) openModal(refusModalElMedecin);
            } else if (modalTargetId === '#annulationRdvMedecinModal') {
                if (rdvIdInputAnnulElMedecin) rdvIdInputAnnulElMedecin.value = rdvId;
                if (motifTextareaAnnulElMedecin) motifTextareaAnnulElMedecin.value = '';
                updateWordCountMedecinVisuals();
                if (annulationModalElMedecin) openModal(annulationModalElMedecin);
            }
        });
    }
    function updateWordCountMedecinVisuals() {
        if (!motifTextareaAnnulElMedecin || !wordCountDisplayAnnulMedecin || !errorMotifDisplayAnnulMedecin) return;
        const text = motifTextareaAnnulElMedecin.value.trim();
        const words = text === '' ? 0 : text.split(/\s+/).filter(word => word.length > 0).length;
        wordCountDisplayAnnulMedecin.textContent = `${words} mot(s). Minimum 10 mots requis.`;
        if (words < 10) {
            wordCountDisplayAnnulMedecin.style.color = 'var(--color-danger)';
            errorMotifDisplayAnnulMedecin.textContent = 'Le motif doit contenir au moins 10 mots.';
            errorMotifDisplayAnnulMedecin.style.display = 'block';
        } else {
            wordCountDisplayAnnulMedecin.style.color = 'var(--text-color-muted)';
            errorMotifDisplayAnnulMedecin.textContent = '';
            errorMotifDisplayAnnulMedecin.style.display = 'none';
        }
    }
    if (motifTextareaAnnulElMedecin) {
        motifTextareaAnnulElMedecin.addEventListener('input', updateWordCountMedecinVisuals);
        if (annulationModalElMedecin && annulationModalElMedecin.style.display === 'block') {
            updateWordCountMedecinVisuals();
        }
    }
    const formAnnulationElMedecin = document.getElementById('formAnnulationRdvMedecin');
    if(formAnnulationElMedecin) {
        formAnnulationElMedecin.addEventListener('submit', function(event) {
            const motif = motifTextareaAnnulElMedecin.value.trim();
            const nbMots = motif === '' ? 0 : motif.split(/\s+/).filter(word => word.length > 0).length;
            if (nbMots < 10) {
                event.preventDefault();
                if(typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'Motif insuffisant', text: 'Votre justification pour l\'annulation doit contenir au moins 10 mots.', confirmButtonColor: 'var(--color-danger)' });
                if (errorMotifDisplayAnnulMedecin) { errorMotifDisplayAnnulMedecin.textContent = 'Le motif doit contenir au moins 10 mots.'; errorMotifDisplayAnnulMedecin.style.display = 'block'; }
                return;
            }
            if (errorMotifDisplayAnnulMedecin) { errorMotifDisplayAnnulMedecin.textContent = ''; errorMotifDisplayAnnulMedecin.style.display = 'none';}
        });
    }
    const urlParamsRdvMedecin = new URLSearchParams(window.location.search);
    const errorMotifRdvIdActionMed = urlParamsRdvMedecin.get('error_motif_rdv_action'); 
    if (errorMotifRdvIdActionMed) {
        const annulationButtonForErrorMed = document.querySelector(`button[data-modal-target="#annulationRdvMedecinModal"][data-rdv-id="${errorMotifRdvIdActionMed}"]`);
        const refusButtonForErrorMed = document.querySelector(`button[data-modal-target="#refusRdvMedecinModal"][data-rdv-id="${errorMotifRdvIdActionMed}"]`);
        if (annulationButtonForErrorMed && annulationModalElMedecin) {
            if (rdvIdInputAnnulElMedecin) rdvIdInputAnnulElMedecin.value = errorMotifRdvIdActionMed;
            openModal(annulationModalElMedecin);
            if (motifTextareaAnnulElMedecin) {
                motifTextareaAnnulElMedecin.focus();
                updateWordCountMedecinVisuals();
            }
        } else if (refusButtonForErrorMed && refusModalElMedecin) {
             if (rdvIdInputRefusElMedecin) rdvIdInputRefusElMedecin.value = errorMotifRdvIdActionMed;
             openModal(refusModalElMedecin);
        }
    }
}

function initProfilePage() {
    function setupPhotoPreview(inputId, previewId, currentPicId) {
        const photoInput = document.getElementById(inputId);
        const photoPreview = document.getElementById(previewId);
        const currentPic = document.getElementById(currentPicId);
        if (photoInput && photoPreview && currentPic) {
            photoInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        photoPreview.src = e.target.result;
                        photoPreview.style.display = 'block';
                        currentPic.style.display = 'none';
                    }
                    reader.readAsDataURL(file);
                } else {
                    photoPreview.style.display = 'none';
                    photoPreview.src = '#';
                    currentPic.style.display = 'block';
                }
            });
        }
    }
    setupPhotoPreview('photoInputPatient', 'photoPreviewPatient', 'currentProfilePicPatient');
    setupPhotoPreview('photoInputMedecin', 'photoPreviewMedecin', 'currentProfilePicMedecin');
    if (document.getElementById('mapPickLocation')) {
        initProfileMapPicker();
    }
}

function initProfileMapPicker() {
    const mapContainer = document.getElementById('mapPickLocation');
    const latInput = document.getElementById('latitude-profil-med-hidden');
    const lonInput = document.getElementById('longitude-profil-med-hidden');
    const coordsDisplay = document.getElementById('selectedCoordsDisplay');
    const findCoordsButton = document.getElementById('findCoordsButton');
    const coordsHelperDiv = document.getElementById('coordsHelper');
    if (!mapContainer || !latInput || !lonInput || !coordsDisplay || typeof L === 'undefined') {
        if (mapContainer && typeof L === 'undefined') {
            mapContainer.innerHTML = "<p class='text-center text-danger'>La librairie de carte (Leaflet) n'a pas pu être chargée.</p>";
        }
        return;
    }
    let defaultLat = 33.5731;
    let defaultLon = -7.5898;
    let defaultZoom = 6;
    let currentMarker = null;
    if (typeof initialProfileCoords !== 'undefined' && 
        initialProfileCoords.lat !== null && initialProfileCoords.lng !== null &&
        !isNaN(parseFloat(initialProfileCoords.lat)) && !isNaN(parseFloat(initialProfileCoords.lng)) ) {
        defaultLat = parseFloat(initialProfileCoords.lat);
        defaultLon = parseFloat(initialProfileCoords.lng);
        defaultZoom = 15;
    }
    const map = L.map(mapContainer).setView([defaultLat, defaultLon], defaultZoom);
    L.tileLayer(TILE_LAYER_URL_SATELLITE, { attribution: TILE_LAYER_ATTRIBUTION_SATELLITE, maxZoom: 19 }).addTo(map);
    if (typeof initialProfileCoords !== 'undefined' &&
        initialProfileCoords.lat !== null && initialProfileCoords.lng !== null &&
        !isNaN(parseFloat(initialProfileCoords.lat)) && !isNaN(parseFloat(initialProfileCoords.lng))) {
        currentMarker = L.marker([parseFloat(initialProfileCoords.lat), parseFloat(initialProfileCoords.lng)]).addTo(map);
        coordsDisplay.textContent = `Lat: ${parseFloat(initialProfileCoords.lat).toFixed(6)}, Lng: ${parseFloat(initialProfileCoords.lng).toFixed(6)}`;
    } else {
        coordsDisplay.textContent = 'Lat: Non définies, Lng: Non définies';
    }
    if (typeof GeoSearch !== 'undefined' && typeof GeoSearch.OpenStreetMapProvider !== 'undefined') {
        const provider = new GeoSearch.OpenStreetMapProvider();
        const searchControl = new GeoSearch.GeoSearchControl({
            provider: provider, style: 'bar', showMarker: false,
            showPopup: false, autoClose: true, retainZoomLevel: false,
            animateZoom: true, keepResult: true, searchLabel: 'Rechercher une adresse...'
        });
        map.addControl(searchControl);
        map.on('geosearch/showlocation', function (result) {
            map.setView([result.location.y, result.location.x], 16);
            updateMarkerAndInputs(result.location.y, result.location.x);
        });
    }
    function updateMarkerAndInputs(lat, lon) {
        if (currentMarker) {
            map.removeLayer(currentMarker);
        }
        currentMarker = L.marker([lat, lon]).addTo(map);
        latInput.value = lat.toFixed(8);
        lonInput.value = lon.toFixed(8);
        coordsDisplay.textContent = `Lat: ${lat.toFixed(6)}, Lng: ${lon.toFixed(6)}`;
    }
    map.on('click', function(e) {
        updateMarkerAndInputs(e.latlng.lat, e.latlng.lng);
    });
    if (findCoordsButton && coordsHelperDiv) {
        findCoordsButton.addEventListener('click', () => {
            coordsHelperDiv.style.display = coordsHelperDiv.style.display === 'none' ? 'block' : 'none';
        });
    }
}

function initGestionDispoMedecinPage() {
    const typeExceptionSelect = document.getElementById('exc_type_exception');
    const timeFieldsExceptionDiv = document.getElementById('time_fields_exception_form');
    if (typeExceptionSelect && timeFieldsExceptionDiv) {
        const toggleTimeFields = () => {
            const heureDebutInput = document.getElementById('exc_heure_debut');
            const heureFinInput = document.getElementById('exc_heure_fin');
            const heuresSaisies = heureDebutInput && (heureDebutInput.value !== '' || (heureFinInput && heureFinInput.value !== ''));
            if (typeExceptionSelect.value !== 'non_travaille' || (typeExceptionSelect.value === 'non_travaille' && heuresSaisies) ) {
                timeFieldsExceptionDiv.style.display = 'grid';
            } else {
                timeFieldsExceptionDiv.style.display = 'none';
                if (typeExceptionSelect.value === 'non_travaille' && !heuresSaisies) {
                    if(heureDebutInput) heureDebutInput.value = '';
                    if(heureFinInput) heureFinInput.value = '';
                }
            }
        };
        typeExceptionSelect.addEventListener('change', toggleTimeFields);
        toggleTimeFields();
    }
}

function initReponseEmailModal() {
    const modal = document.getElementById('modalRepondreEmail');
    if (!modal) return;
    const patientIdInput = modal.querySelector('#reponsePatientIdDestinataire');
    const patientEmailInput = modal.querySelector('#reponsePatientEmailDestinataire');
    const destinataireAffichageInput = modal.querySelector('#reponseDestinataireAffichage');
    const sujetInput = modal.querySelector('#reponseSujet');
    const messageTextarea = modal.querySelector('#reponseMessage');
    document.querySelectorAll('.open-reponse-email-modal').forEach(button => {
        button.addEventListener('click', function() {
            if(patientIdInput) patientIdInput.value = this.dataset.patientId;
            if(patientEmailInput) patientEmailInput.value = this.dataset.patientEmail;
            if(destinataireAffichageInput) destinataireAffichageInput.value = `${this.dataset.patientNom} <${this.dataset.patientEmail}>`;
            if(sujetInput) sujetInput.value = this.dataset.sujetOriginal || `Réponse à votre message`;
            if (typeof tinymce !== 'undefined') {
                let editor = tinymce.get('reponseMessage');
                if (editor) {
                    editor.setContent('');
                } else {
                    tinymce.init({
                        selector: '#reponseMessage', height: 250, menubar: false,
                        plugins: ['lists link charmap emoticons help wordcount'],
                        toolbar: 'undo redo | styles | bold italic underline | bullist numlist | link emoticons | help',
                        language: 'fr_FR', statusbar: true, elementpath: false
                    });
                }
            } else {
                 if(messageTextarea) messageTextarea.value = '';
            }
            openModal(modal);
        });
    });
}

function initAdminSendSpecificEmailPage() {
    const searchInput = document.getElementById('user_search_input_email_spec');
    const searchResultsContainer = document.getElementById('search_results_dropdown_email_spec');
    const searchResultsUl = searchResultsContainer ? searchResultsContainer.querySelector('ul') : null;
    const selectedUsersDisplay = document.getElementById('selected_users_display_email_spec');
    const hiddenInputsContainer = document.getElementById('selected_user_ids_hidden_inputs_email_spec');
    const userTypeSelect = document.getElementById('user_type_search_email_spec');
    const errorSelectedUsers = document.getElementById('error_selected_users');
    const form = document.getElementById('sendSpecificEmailForm');
    if (!searchInput || !searchResultsContainer || !searchResultsUl || !selectedUsersDisplay || !hiddenInputsContainer || !userTypeSelect || !form || !errorSelectedUsers) return;
    let searchTimeout;
    let selectedUsers = new Set();
    selectedUsersDisplay.querySelectorAll('.selected-user-tag').forEach(tag => {
        selectedUsers.add(tag.dataset.userId);
        tag.querySelector('.remove-user').addEventListener('click', function() {
            removeSelectedUser(tag.dataset.userId);
        });
    });
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const searchTerm = searchInput.value.trim();
        const userType = userTypeSelect.value;
        if (searchTerm.length < 2) {
            searchResultsContainer.style.display = 'none';
            return;
        }
        searchTimeout = setTimeout(async () => {
            try {
                const response = await fetch(`traitement_admin_send_email.php?action=search_users&term=${encodeURIComponent(searchTerm)}&type=${userType}`);
                if (!response.ok) {
                    let errorMsg = `Erreur réseau lors de la recherche. Statut: ${response.status}`;
                    try {
                        const errData = await response.json();
                        if (errData && errData.error) errorMsg = errData.error;
                    } catch (e) { }
                    throw new Error(errorMsg);
                }
                const data = await response.json();
                if (data.error) {
                     searchResultsUl.innerHTML = `<li>Erreur: ${escapeHtml(data.error)}</li>`;
                } else {
                     displaySearchResults(data.users || []);
                }
            } catch (error) {
                searchResultsUl.innerHTML = `<li>Erreur de recherche: ${escapeHtml(error.message)}</li>`;
            } finally {
                searchResultsContainer.style.display = 'block';
            }
        }, 300);
    });
    document.addEventListener('click', function(event) {
        if (searchResultsContainer && !searchInput.contains(event.target) && !searchResultsContainer.contains(event.target)) {
            searchResultsContainer.style.display = 'none';
        }
    });
    function displaySearchResults(users) {
        searchResultsUl.innerHTML = '';
        if (!users || users.length === 0) {
            searchResultsUl.innerHTML = '<li>Aucun utilisateur trouvé.</li>';
        } else {
            users.forEach(user => {
                const li = document.createElement('li');
                const userInfoSpan = document.createElement('span');
                userInfoSpan.textContent = `${escapeHtml(user.prenom)} ${escapeHtml(user.nom)} (${escapeHtml(user.email)})`;
                li.appendChild(userInfoSpan);
                const addButton = document.createElement('button');
                addButton.type = 'button';
                addButton.classList.add('add-user-btn');
                addButton.textContent = 'Ajouter';
                addButton.addEventListener('click', function() {
                    addSelectedUser(user.id, userTypeSelect.value, `${escapeHtml(user.prenom)} ${escapeHtml(user.nom)}`, escapeHtml(user.email));
                    searchInput.value = ''; 
                    searchResultsContainer.style.display = 'none';
                });
                li.appendChild(addButton);
                searchResultsUl.appendChild(li);
            });
        }
        searchResultsContainer.style.display = 'block';
    }
    function addSelectedUser(id, type, name, email) {
        const userIdString = `${type}:${id}`;
        if (selectedUsers.has(userIdString)) return;
        selectedUsers.add(userIdString);
        const tag = document.createElement('span');
        tag.classList.add('selected-user-tag');
        tag.dataset.userId = userIdString;
        tag.innerHTML = `${name} <${email}> `;
        const removeBtn = document.createElement('span');
        removeBtn.classList.add('remove-user');
        removeBtn.innerHTML = '×';
        removeBtn.title = 'Retirer';
        removeBtn.addEventListener('click', function() {
            removeSelectedUser(userIdString);
        });
        tag.appendChild(removeBtn);
        selectedUsersDisplay.appendChild(tag);
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'selected_user_ids[]';
        hiddenInput.value = userIdString;
        hiddenInputsContainer.appendChild(hiddenInput);
        if (errorSelectedUsers) errorSelectedUsers.textContent = '';
    }
    function removeSelectedUser(userIdString) {
        selectedUsers.delete(userIdString);
        const tagToRemove = selectedUsersDisplay.querySelector(`.selected-user-tag[data-user-id="${userIdString}"]`);
        if (tagToRemove) selectedUsersDisplay.removeChild(tagToRemove);
        const hiddenInputToRemove = hiddenInputsContainer.querySelector(`input[value="${userIdString}"]`);
        if (hiddenInputToRemove) hiddenInputsContainer.removeChild(hiddenInputToRemove);
    }
    form.addEventListener('submit', function(e) {
        if (selectedUsers.size === 0) {
            if (errorSelectedUsers) errorSelectedUsers.textContent = 'Veuillez sélectionner au moins un destinataire.';
            e.preventDefault();
        } else {
             if (errorSelectedUsers) errorSelectedUsers.textContent = '';
        }
    });
}