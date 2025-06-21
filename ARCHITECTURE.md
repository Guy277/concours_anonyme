# Architecture du Projet - Plateforme de Concours avec Correction Anonyme

## Structure des Dossiers

```
concours_anonyme/
│
├── ARCHITECTURE.md
├── admin/
│   ├── attribuer_copies.php
│   ├── comparaison_concours.php
│   ├── concours_detail.php
│   ├── gerer_correcteur.php
│   ├── gestion_concours.php
│   ├── gestion_copies_simple.php
│   ├── statistiques_globales.php
│   ├── telecharger_copie.php
│   ├── valider_corrections.php
│   ├── visualiser_copie.php
│   ├── voir_copie.php
│   └── copies/
│       └── voir.php
├── api/
│   ├── check_titre_concours_modification.php
│   ├── check_titre_concours.php
│   └── notifications.php
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── main.js
├── check_admin.php
├── concours/
│   ├── creer.php
│   ├── gerer_correcteurs.php
│   ├── liste.php
│   └── modifier.php
├── copies/
│   ├── attribuer.php
│   ├── deposer.php
│   ├── liste.php
│   ├── mes_copies.php
│   ├── modifier.php
│   ├── telecharger_copie.php
│   └── voir.php
├── corrections/
│   ├── ajouter.php
│   ├── copies_a_corriger.php
│   ├── evaluer.php
│   ├── evaluer_moderne.php
│   ├── liste.php
│   ├── mes_corrections.php
│   ├── telecharger_copie.php
│   ├── voir.php
│   └── voir_copie.php
├── dashboard/
│   ├── admin.php
│   ├── candidat.php
│   └── correcteur.php
├── database/
│   ├── add_unique_constraint_to_concours_titre.sql
│   ├── contacts.sql
│   ├── execute_updates.php
│   ├── init.php
│   ├── schema.sql
│   ├── update_attributions_table.sql
│   └── update_statut_enum.sql
├── download_file.php
├── exports/
│   ├── export_process.php
│   └── resultats.php
├── includes/
│   ├── anonymisation.php
│   ├── auth.php
│   ├── config.php
│   ├── dashboard_nav.php
│   ├── error_handler.php
│   ├── footer.php
│   ├── footer_admin.php
│   ├── header.php
│   ├── header_admin.php
│   ├── note_calculator.php
│   ├── notifications.php
│   └── submission_rules.php
├── index.php
├── login.php
├── logout.php
├── logs/
│   └── error.log
├── mon-profil.php
├── register.php
├── resultats/
│   ├── mes_resultats.php
│   └── voir.php
├── uploads/
│   └── copies/
├── utilisateurs/
│   ├── creer.php
│   ├── gerer.php
│   └── modifier.php
└── vendor/
    ├── phpoffice/
    │   └── phpspreadsheet/
    └── tecnickcom/
        └── tcpdf/
```

## Description des Composants

### 1. Fichiers Racine
- **index.php** : Page d'accueil et redirection selon le rôle
- **login.php** : Interface de connexion
- **logout.php** : Déconnexion et destruction de session
- **register.php** : Inscription de nouveaux utilisateurs
- **mon-profil.php** : Gestion du profil utilisateur
- **check_admin.php** : Utilitaire de vérification des identifiants admin par défaut.
- **download_file.php** : Point d'entrée pour le téléchargement sécurisé de fichiers.

### 2. Assets (Ressources Statiques)
- **css/style.css** : Feuille de style principale. Contient les styles pour l'ensemble de l'application, y compris les formulaires, les tableaux de bord et les composants d'interface.
- **js/main.js** : Fichier JavaScript principal. Gère les interactions dynamiques, les validations de formulaire côté client et les appels API (notifications).
- **api/check_titre_concours.php** : API pour vérifier en temps réel si un titre de concours existe déjà (utilisé lors de la création).
- **api/check_titre_concours_modification.php** : API similaire à la précédente, mais utilisée lors de la modification pour exclure le concours actuel de la vérification.
- **api/notifications.php** : API pour la gestion des notifications (marquer comme lues, supprimer).
- **database/add_unique_constraint_to_concours_titre.sql** : Script SQL pour ajouter une contrainte d'unicité sur le titre des concours, empêchant les doublons au niveau de la base de données.
- **database/contacts.sql** : Données de contact (potentiellement pour une page de contact).
- **database/execute_updates.php** : Script PHP pour exécuter des mises à jour de la base de données de manière programmatique.
- **database/init.php** : Script d'initialisation de la base de données.
- **database/schema.sql** : Schéma complet de la base de données, définissant toutes les tables, colonnes et relations.
- **database/update_attributions_table.sql** : Script de mise à jour pour la table des attributions.
- **database/update_statut_enum.sql** : Script de mise à jour pour les valeurs énumérées des statuts.

### 3. Includes (Composants Réutilisables)
- **config.php** : Configuration de la base de données et paramètres globaux
- **auth.php** : Fonctions d'authentification
- **header.php** : En-tête commun à toutes les pages
- **footer.php** : Pied de page commun
- **header_admin.php** : En-tête spécifique à l'administration
- **footer_admin.php** : Pied de page spécifique à l'administration
- **dashboard_nav.php** : Navigation du tableau de bord selon le rôle
- **anonymisation.php** : Fonctions d'anonymisation des copies
- **submission_rules.php** : Règles de soumission et validation
- **note_calculator.php** : Calcul automatique des notes
- **notifications.php** : Système de notifications
- **error_handler.php** : Gestion centralisée des erreurs

### 4. Dashboard (Tableaux de Bord)
- **admin.php** : Interface d'administration principale
  - Gestion des concours
  - Gestion des utilisateurs
  - Statistiques globales
- **candidat.php** : Interface candidat
  - Liste des concours disponibles
  - Suivi des copies déposées
- **correcteur.php** : Interface correcteur
  - Liste des copies à corriger
  - Statistiques de correction

### 5. Admin (Fonctionnalités Administratives)
- **attribuer_copies.php** : Attribution des copies aux correcteurs
- **valider_corrections.php** : Validation finale des corrections
- **statistiques_globales.php** : Statistiques détaillées du système
- **comparaison_concours.php** : Comparaison entre différents concours
- **gerer_correcteur.php** : Gestion des correcteurs
- **gestion_concours.php** : Gestion avancée des concours
- **gestion_copies_simple.php** : Interface simplifiée de gestion des copies
- **concours_detail.php** : Détails complets d'un concours
- **telecharger_copie.php** : Téléchargement administratif de copies
- **visualiser_copie.php** : Visualisation des copies
- **voir_copie.php** : Consultation des copies
- **copies/voir.php** : Vue détaillée des copies (admin)

### 6. Concours (Gestion des Concours)
- **creer.php** : Création de nouveaux concours
- **modifier.php** : Modification des concours existants
- **liste.php** : Liste des concours disponibles
- **gerer_correcteurs.php** : Attribution des correcteurs aux concours

### 7. Copies (Gestion des Copies)
- **deposer.php** : Interface de dépôt des copies
- **mes_copies.php** : Suivi des copies déposées par le candidat
- **voir.php** : Consultation détaillée d'une copie
- **modifier.php** : Modification des copies (si autorisé)
- **liste.php** : Liste des copies
- **attribuer.php** : Attribution des copies aux correcteurs
- **telecharger_copie.php** : Téléchargement de copies

### 8. Corrections (Système de Correction)
- **evaluer_moderne.php** : Interface moderne d'évaluation
- **evaluer.php** : Interface classique d'évaluation
- **mes_corrections.php** : Suivi des corrections effectuées
- **copies_a_corriger.php** : Liste des copies à corriger
- **voir.php** : Consultation des corrections
- **voir_copie.php** : Vue copie avec corrections
- **ajouter.php** : Ajout de corrections
- **liste.php** : Liste des corrections
- **telecharger_copie.php** : Téléchargement pour correction

### 9. Résultats (Consultation des Résultats)
- **mes_resultats.php** : Résultats personnels du candidat
- **voir.php** : Consultation détaillée des résultats

### 10. Utilisateurs (Gestion des Utilisateurs)
- **gerer.php** : Gestion complète des utilisateurs
- **creer.php** : Création de nouveaux utilisateurs
- **modifier.php** : Modification des profils utilisateurs

### 11. Exports (Export de Données)
- **resultats.php** : Export des résultats
- **export_process.php** : Traitement des exports

### 12. API (Interface de Programmation)
- **notifications.php** : API pour les notifications

### 13. Database (Base de Données)
- **schema.sql** : Structure complète de la base de données
- **init.php** : Initialisation de la base de données
- **contacts.sql** : Données de contact
- **execute_updates.php** : Scripts de mise à jour
- **update_statut_enum.sql** : Mise à jour des statuts
- **update_attributions_table.sql** : Mise à jour des attributions

### 14. Vendor (Dépendances Externes)
- **phpoffice/phpspreadsheet/** : Bibliothèque pour la création et la manipulation de fichiers Excel. Essentielle pour les fonctionnalités d'export.
- **tecnickcom/tcpdf/** : Bibliothèque pour la génération de documents PDF.

### 15. Logs (Journalisation)
- **error.log** : Fichier où sont enregistrées les erreurs PHP et les exceptions, crucial pour le débogage.

### 16. Uploads (Stockage des Fichiers)
- **copies/** : Dossier de destination pour toutes les copies déposées par les candidats.
- **Authentification et gestion des permissions** : Le système utilise les sessions PHP et des vérifications de rôle (`admin`, `correcteur`, `candidat`) pour sécuriser l'accès aux différentes sections.
- **Protection contre les failles courantes** : Les entrées utilisateur sont systématiquement nettoyées avec `htmlspecialchars` pour prévenir les attaques XSS. Les requêtes SQL sont préparées avec PDO pour empêcher les injections SQL.
- **Validation des données** : Le système inclut des validations côté client (JavaScript) et côté serveur (PHP) pour assurer l'intégrité des données soumises. Une contrainte `UNIQUE` a été ajoutée sur le titre des concours pour éviter les doublons.
- **Correction des bugs de duplication** : Plusieurs requêtes SQL complexes ont été réécrites en utilisant des sous-requêtes et des agrégations (`GROUP BY`, `TRIM()`) pour fournir des statistiques fiables et éliminer les affichages de données en double qui affectaient plusieurs pages.
- **Améliorations UX/UI** : L'interface a été améliorée avec de nouvelles fonctionnalités (suppression de notifications), des corrections de liens brisés et des ajustements de style pour améliorer l'ergonomie (réduction de la taille des boîtes de validation, styles des formulaires de connexion).

## Fonctionnalités Principales

### 1. Gestion des Concours
- Création et modification de concours
- Attribution des correcteurs
- Gestion des critères d'évaluation
- Suivi des statuts

### 2. Système de Correction Anonyme
- Anonymisation automatique des copies
- Attribution aléatoire aux correcteurs
- Grilles d'évaluation personnalisables
- Calcul automatique des notes

### 3. Tableaux de Bord
- Interface adaptée selon le rôle (admin, correcteur, candidat)
- Statistiques en temps réel
- Notifications automatiques

### 4. Export et Reporting
- Export Excel des résultats
- Génération de rapports PDF
- Statistiques détaillées

### 5. Sécurité
- Authentification robuste
- Gestion des sessions
- Validation des fichiers
- Protection contre les injections

## Technologies Utilisées

- **Backend** : PHP 8.2+
- **Base de données** : MySQL/MariaDB avec PDO
- **Frontend** : HTML5, CSS3, JavaScript (avec Fetch API pour les appels asynchrones)
- **Dépendances Externes** : PhpSpreadsheet (pour Excel), TCPDF (pour PDF)
- **Serveur** : XAMPP (Apache + MySQL)

## Dépendances Externes

- **PhpSpreadsheet** : Utilisée dans `exports/export_process.php` pour générer des fichiers au format tableur.
- **TCPDF** : Utilisée pour la génération de documents PDF, potentiellement dans les exports ou pour d'autres types de rapports.

## Sécurité

1. **Authentification**
   - Contrôle d'accès basé sur les rôles et les sessions.
   - Protection des pages et des API nécessitant une authentification.

2. **Validation des Données**
   - **Côté client** : Validations JavaScript pour un retour rapide à l'utilisateur.
   - **Côté serveur** : Validations PHP robustes qui sont la source de vérité.
   - **Prévention des injections SQL** : Utilisation systématique des requêtes préparées avec PDO.
   - **Prévention XSS** : Nettoyage des données affichées avec `htmlspecialchars`.
   - **Contrainte d'unicité** : Contrainte `UNIQUE` sur le titre des concours dans la base de données.

3. **Gestion des Fichiers**
   - Validation du type et de la taille des fichiers uploadés.
   - Noms de fichiers générés de manière sécurisée pour éviter les conflits.
   - Dossier `uploads/` stocké en dehors des répertoires directement accessibles si possible, ou protégé.

4. **Journalisation et Débogage**
   - Les erreurs sont enregistrées dans `logs/error.log`.
   - Les fichiers de débogage temporaires (`check_duplicates...`, etc.) ont été supprimés.

## Installation et Configuration

1. **Prérequis**
   - XAMPP avec PHP 8.2+ et MySQL/MariaDB.
   - Navigateur web moderne.

2. **Installation**
   - Cloner le projet dans le dossier `htdocs` de XAMPP.
   - Créer une base de données et importer la structure depuis `database/schema.sql`.
   - Exécuter le script `database/init.php` pour insérer les données initiales (comme le compte administrateur).
   - Appliquer les scripts de mise à jour nécessaires depuis le dossier `database/`.

3. **Configuration**
   - Modifier le fichier `includes/config.php` pour y mettre vos identifiants de base de données et l'URL de l'application (`APP_URL`).
   - S'assurer que le serveur Apache a les permissions d'écriture dans le dossier `uploads/copies/`.

## Maintenance

- **Sauvegardes** : Planifier des sauvegardes régulières de la base de données et du dossier `uploads`.
- **Surveillance** : Consulter régulièrement le fichier `logs/error.log` pour identifier et corriger les problèmes de manière proactive.
- **Dépendances** : Penser à mettre à jour les dépendances (PhpSpreadsheet, TCPDF) si nécessaire.
- **Nettoyage de la base de données** : Mettre en place une stratégie pour nettoyer les données potentiellement dupliquées (par exemple, les concours avec des titres similaires contenant des espaces).

## Taille du Projet

- **Total** : ~21 MB
- **Code source** : ~2 MB
- **Dépendances (vendor)** : ~15 MB
- **Données utilisateur (uploads)** : ~4 MB
- **Logs** : ~120 KB

## Notes sur les Évolutions Récentes

- Le projet a fait l'objet d'une **chasse aux bugs intensive** pour résoudre des problèmes de duplication de données. La solution a consisté à réécrire de nombreuses requêtes SQL pour utiliser des agrégations robustes (`GROUP BY TRIM(titre)`).
- De **nouvelles routes d'API** ont été ajoutées pour la vérification en temps réel des titres de concours, améliorant l'expérience utilisateur lors de la création et de la modification.
- L'**interface utilisateur a été améliorée** sur plusieurs points : refonte de la page de validation des corrections, ajout de la suppression des notifications, correction des formulaires de connexion.
- La **sécurité a été renforcée** par l'ajout d'une contrainte `UNIQUE` au niveau de la base de données.
- Le **code a été nettoyé** par la suppression de plusieurs fichiers de test et de débogage obsolètes.