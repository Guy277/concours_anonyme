// Fichier main.js

// Fonction pour gérer la soumission des formulaires
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Vérification des champs requis
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                } else {
                    field.classList.remove('error');
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires.');
            }
        });
    });
});

// Fonction pour gérer l'aperçu des fichiers PDF
function handleFileSelect(event) {
    const file = event.target.files[0];
    if (file && file.type === 'application/pdf') {
        const reader = new FileReader();
        reader.onload = function(e) {
            const pdfViewer = document.getElementById('pdf-viewer');
            if (pdfViewer) {
                pdfViewer.src = e.target.result;
            }
        };
        reader.readAsDataURL(file);
    }
}

// Fonction pour gérer les filtres
function handleFilterChange(event) {
    const form = event.target.closest('form');
    if (form) {
        form.submit();
    }
}

// Fonction pour gérer la déconnexion
function handleLogout() {
    if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
        window.location.href = 'logout.php';
    }
}

// Fonction pour gérer la suppression
function handleDelete(event, message) {
    if (!confirm(message || 'Êtes-vous sûr de vouloir supprimer cet élément ?')) {
        event.preventDefault();
    }
}

// Ajout des écouteurs d'événements
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des fichiers PDF
    const fileInputs = document.querySelectorAll('input[type="file"][accept=".pdf"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', handleFileSelect);
    });

    // Gestion des filtres
    const filterSelects = document.querySelectorAll('select[name="concours"]');
    filterSelects.forEach(select => {
        select.addEventListener('change', handleFilterChange);
    });

    // Gestion de la déconnexion
    const logoutLink = document.querySelector('a[href*="logout"]');
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            handleLogout();
        });
    }

    // Gestion des suppressions
    const deleteLinks = document.querySelectorAll('a[href*="supprimer"]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            handleDelete(e);
        });
    });
});

// Gestion des notifications
function markNotificationAsRead(index) {
    const formData = new URLSearchParams();
    formData.append('action', 'mark_read');
    formData.append('index', index);

    fetch('api/notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert("Erreur : " + (data.message || "Impossible de traiter la demande."));
        }
    })
    .catch(error => console.error('Erreur:', error));
}

function markAllNotificationsAsRead() {
    const formData = new URLSearchParams();
    formData.append('action', 'mark_all_read');

    fetch('api/notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert("Erreur : " + (data.message || "Impossible de traiter la demande."));
        }
    })
    .catch(error => console.error('Erreur:', error));
}

function deleteNotification(index) {
    if (!confirm("Êtes-vous sûr de vouloir supprimer cette notification ?")) {
        return;
    }

    const formData = new URLSearchParams();
    formData.append('action', 'delete');
    formData.append('index', index);

    fetch('api/notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload(); // Recharger pour voir la liste à jour
        } else {
            alert("Erreur : " + (data.message || "Impossible de supprimer la notification."));
        }
    })
    .catch(error => {
        console.error('Erreur lors de la suppression de la notification:', error);
        alert("Une erreur technique est survenue.");
    });
}

