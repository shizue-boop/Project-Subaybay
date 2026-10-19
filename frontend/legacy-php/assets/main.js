// Project Subaybay - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts if containers exist
    initCharts();

    // Initialize dropdowns
    initDropdowns();
    
    // Initialize mobile sidebar
    initMobileSidebar();
    
    // Initialize image upload
    initImageUpload();
    
    // Handle window resize
    handleResponsive();
    window.addEventListener('resize', handleResponsive);
});

// MODAL FUNCTIONS
function openModal(modalId) {
    const overlay = document.getElementById('modalOverlay');
    const modal = document.getElementById(modalId);

    if (overlay) overlay.classList.add('active');
    if (modal) modal.classList.add('active');
}

function closeModal(modalId) {
    const overlay = document.getElementById('modalOverlay');
    const modal = document.getElementById(modalId);

    if (overlay) overlay.classList.remove('active');
    if (modal) modal.classList.remove('active');
}

function closeAllModals() {
    const overlay = document.getElementById('modalOverlay');
    if (overlay) overlay.classList.remove('active');
    document.querySelectorAll('.modal').forEach(modal => {
        modal.classList.remove('active');
    });
}

// DROPDOWNS
function initDropdowns() {
    document.querySelectorAll('.dropdown').forEach(dropdown => {
        const btn = dropdown.querySelector('.btn-dropdown');
        if(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdown.classList.toggle('open');
            });
        }
    });

    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('open'));
    });
}

// CHART INITIALIZATION
function initCharts() {
    // Reading Level Distribution (Doughnut)
    const levelDistCtx = document.getElementById('levelDistributionChart');
    if(levelDistCtx) {
        new Chart(levelDistCtx, {
            type: 'doughnut',
            data: {
                labels: ['Independent', 'Instructional', 'Frustration'],
                datasets: [{
                    data: window.dashboardChartData?.levels || [0, 0, 0],
                    backgroundColor: ['#22C55E', '#F59E0B', '#EF4444'],
                    borderWidth: 0,
                    cutout: '70%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // Grade-Level Reading Performance (Bar)
    const gradePerfCtx = document.getElementById('gradePerformanceChart');
    if(gradePerfCtx) {
        new Chart(gradePerfCtx, {
            type: 'bar',
            data: {
                labels: window.dashboardChartData?.grades?.labels || [],
                datasets: [
                    {
                        label: 'Independent',
                        data: window.dashboardChartData?.grades?.independent || [],
                        backgroundColor: '#22C55E',
                        borderRadius: 4
                    },
                    {
                        label: 'Instructional',
                        data: window.dashboardChartData?.grades?.instructional || [],
                        backgroundColor: '#F59E0B',
                        borderRadius: 4
                    },
                    {
                        label: 'Frustration',
                        data: window.dashboardChartData?.grades?.frustration || [],
                        backgroundColor: '#EF4444',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 60,
                        ticks: {
                            callback: function(value) { return value + '%'; },
                            font: { size: 11 }
                        }
                    },
                    x: {
                        ticks: { font: { size: 11 } }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, padding: 20, font: { size: 11 } }
                    }
                }
            }
        });
    }

    // Level Distribution by Month (Bar)
    const monthlyCtx = document.getElementById('monthlyDistributionChart');
    if(monthlyCtx) {
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: ['Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [
                    { label: 'Independent', data: [4, 5, 6, 7, 5], backgroundColor: '#22C55E', borderRadius: 3 },
                    { label: 'Instructional', data: [5, 5, 4, 4, 4], backgroundColor: '#3B82F6', borderRadius: 3 },
                    { label: 'Frustration', data: [6, 5, 5, 4, 4], backgroundColor: '#EF4444', borderRadius: 3 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { font: { size: 11 } } },
                    x: { ticks: { font: { size: 11 } } }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, padding: 15, font: { size: 10 } }
                    }
                }
            }
        });
    }

    // Average Reading Score Over Time (Line)
    const scoreCtx = document.getElementById('scoreTrendChart');
    if(scoreCtx) {
        new Chart(scoreCtx, {
            type: 'line',
            data: {
                labels: ['Wk 1', 'Wk 2', 'Wk 3', 'Wk 4', 'Wk 5', 'Wk 6', 'Wk 7', 'Wk 8'],
                datasets: [{
                    label: 'Average Score',
                    data: [58, 62, 60, 66, 72, 70, 76, 79],
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59,130,246,0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#3B82F6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { min: 50, max: 90, ticks: { font: { size: 11 } } },
                    x: { ticks: { font: { size: 11 } } }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
}

// CONFIRM DELETE
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this item?');
}

// FORM VALIDATION
function validateForm(formId) {
    const form = document.getElementById(formId);
    if(!form) return true;

    let valid = true;
    form.querySelectorAll('[required]').forEach(field => {
        if(!field.value.trim()) {
            field.style.borderColor = '#EF4444';
            valid = false;
        } else {
            field.style.borderColor = '';
        }
    });

    return valid;
}

// SEARCH TABLE
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if(!input || !table) return;

    const filter = input.value.toLowerCase();
    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

function filterMaterials() {
    const grid = document.getElementById('matGrid');
    if (!grid) return;

    const search = (document.getElementById('matSearch')?.value || '').trim().toLowerCase();
    const grade = document.getElementById('gradeFilter')?.value || 'All Grades';
    const language = document.getElementById('languageFilter')?.value || '';
    const part = document.getElementById('partFilter')?.value || '';
    const profile = document.getElementById('profileFilter')?.value || 'All Profiles';
    const cards = grid.querySelectorAll('.material-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const matchesSearch = card.textContent.toLowerCase().includes(search);
        const matchesGrade = grade === 'All Grades' || card.dataset.grade === grade || card.dataset.grade === 'All Grades';
        const matchesLanguage = !language || card.dataset.language === language || card.dataset.language === 'All Languages';
        const matchesPart = !part || card.dataset.part === part || card.dataset.part === 'General Reading Practice';
        const matchesProfile = profile === 'All Profiles' || card.dataset.profile === profile || card.dataset.profile === 'All Profiles';
        const visible = matchesSearch && matchesGrade && matchesLanguage && matchesPart && matchesProfile;

        card.style.display = visible ? '' : 'none';
        if (visible) visibleCount += 1;
    });

    const emptyState = document.getElementById('noMaterialsMatch');
    if (emptyState) emptyState.style.display = visibleCount ? 'none' : '';
}

// PRINT REPORT
function printReport() {
    window.print();
}

// EXPORT TO CSV
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if(!table) return;

    let csv = [];
    const rows = table.querySelectorAll('tr');

    rows.forEach(row => {
        let cols = row.querySelectorAll('td, th');
        let rowData = [];
        cols.forEach(col => rowData.push(col.innerText));
        csv.push(rowData.join(','));
    });

    const csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
    const link = document.createElement('a');
    link.setAttribute('href', encodeURI(csvContent));
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// MOBILE SIDEBAR FUNCTIONALITY
function initMobileSidebar() {
    // Create overlay for mobile
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay && window.innerWidth < 768) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        
        overlay.addEventListener('click', closeMobileSidebar);
    }
    
    // Create hamburger menu button if it doesn't exist
    let hamburger = document.querySelector('.hamburger-menu');
    if (!hamburger) {
        const topHeader = document.querySelector('.top-header');
        if (topHeader) {
            hamburger = document.createElement('button');
            hamburger.className = 'hamburger-menu';
            hamburger.innerHTML = '<i class="fa-solid fa-bars"></i>';
            hamburger.addEventListener('click', toggleMobileSidebar);
            topHeader.insertBefore(hamburger, topHeader.firstChild);
        }
    }
    
    // Close sidebar when menu item is clicked on mobile
    if (window.innerWidth < 768) {
        document.querySelectorAll('.sidebar-menu li a').forEach(link => {
            link.addEventListener('click', closeMobileSidebar);
        });
    }
}

function toggleMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.toggle('active');
    }
    if (overlay) {
        overlay.classList.toggle('active');
    }
}

function closeMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.remove('active');
    }
    if (overlay) {
        overlay.classList.remove('active');
    }
}

function openMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) {
        sidebar.classList.add('active');
    }
    if (overlay) {
        overlay.classList.add('active');
    }
}

// RESPONSIVE HANDLER
function handleResponsive() {
    const width = window.innerWidth;
    const sidebar = document.querySelector('.sidebar');
    
    if (width < 768) {
        // Mobile view
        if (sidebar && sidebar.classList.contains('active')) {
            // Keep active state if already open
        } else if (sidebar) {
            sidebar.classList.remove('active');
        }
        
        // Initialize mobile sidebar if needed
        if (!document.querySelector('.hamburger-menu')) {
            initMobileSidebar();
        }
    } else {
        // Desktop view
        if (sidebar) {
            sidebar.classList.remove('active');
        }
        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.classList.remove('active');
        }
        
        // Show hamburger only on mobile
        const hamburger = document.querySelector('.hamburger-menu');
        if (hamburger) {
            hamburger.style.display = width < 768 ? 'flex' : 'none';
        }
    }
}

// IMAGE UPLOAD FUNCTIONALITY
function initImageUpload() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('profileImageInput');
    const uploadForm = document.getElementById('uploadForm');
    
    if (!uploadArea || !fileInput) {
        console.log('Upload area or file input not found');
        return;
    }
    
    // Make upload area label properly clickable
    uploadArea.addEventListener('click', function(e) {
        // If clicking directly on the label, the input should trigger automatically
        // But let's ensure it does
        if (e.target !== fileInput) {
            fileInput.click();
        }
    });
    
    // File selected - auto submit
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            const file = this.files[0];
            const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            console.log('File selected:', file.name, 'Type:', file.type, 'Size:', file.size);
            
            // Validate file
            if (!validTypes.includes(file.type)) {
                alert('Only image files (JPG, PNG, GIF, WebP) are allowed.');
                fileInput.value = '';
                return;
            }
            
            if (file.size > maxSize) {
                alert('Image size must not exceed 5MB.');
                fileInput.value = '';
                return;
            }
            
            // Show loading state
            uploadArea.style.opacity = '0.6';
            uploadArea.style.pointerEvents = 'none';
            
            // Auto-submit form
            if (uploadForm) {
                console.log('Submitting form...');
                uploadForm.submit();
            }
        }
    });
    
    // Drag and drop
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.classList.add('dragover');
    });
    
    uploadArea.addEventListener('dragleave', (e) => {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.classList.remove('dragover');
    });
    
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            // Set the file input files
            fileInput.files = files;
            // Trigger change event to validate and submit
            const event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
        }
    });
}