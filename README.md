GKR Klantportaal
Het GKR Klantportaal is een veilig, overzichtelijk platform gebouwd met het Laravel-framework. Dit portaal fungeert als de centrale brug tussen de organisatie GKR en haar klanten. Het stelt klanten in staat om de status van hun projecten in te zien, documenten te beheren, opmerkingen te plaatsen en afspraken in te plannen.

Inhoudsopgave
Functionaliteiten

Systeemeisen

Installatie & Lokale Setup

Rollen en Rechten

Projectstructuur

Deployment (Railway)

1. Functionaliteiten
Het platform is opgebouwd rondom een aantal kernmodules om de samenwerking met de klant soepel te laten verlopen:

Multi-tenant Projectbeheer: Klanten zien uitsluitend de projecten en gegevens die aan hun eigen account zijn gekoppeld.

Documentenbeheer: Veilige opslag en overdracht van projectdocumenten (zoals handleidingen, rapportages of ontwerpen).

Interactiesysteem: Mogelijkheid voor zowel de klant als de administrator om opmerkingen en feedback achter te laten bij specifieke projecten of documenten.

Afsprakensysteem: Klanten kunnen direct beschikbare datums en tijden inzien en een afspraak inplannen met GKR.

Wachtwoordbeveiliging (Site-wide): Extra beveiligingslaag via SitePasswordProtection middleware om de applicatie in test- of stagingomgevingen af te schermen voor onbevoegden.

2. Systeemeisen
Zorg ervoor dat de volgende software op je lokale machine is geïnstalleerd:

PHP (versie 8.2 of hoger aanbevolen)

Composer (voor PHP package management)

Node.js & NPM (voor het compileren van de frontend assets)

Een database (zoals MySQL, PostgreSQL of SQLite)

3. Installatie & Lokale Setup
Volg deze stappen om het project lokaal op te starten:

Stap 1: Clone de repository

Bash
git clone <repository-url>
cd Klantportaal-GKR
Stap 2: Installeer de PHP-dependencies

Bash
composer install
Stap 3: Installeer de Frontend-dependencies

Bash
npm install
Stap 4: Omgevingsvariabelen instellen
Kopieer het voorbeeld-omgevingsbestand naar een live .env bestand:

Bash
cp .env.example .env
Open het .env bestand en vul je databasegegevens in (zoals DB_DATABASE, DB_USERNAME, DB_PASSWORD).

Stap 5: Genereer de applicatiesleutel

Bash
php artisan key:generate
Stap 6: Database migraties en seeders uitvoeren
Maak de tabellen aan en vul de database met eventuele testgegevens:

Bash
php artisan migrate --seed
Stap 7: Applicatie lokaal starten
Start de lokale PHP-ontwikkelserver:

Bash
php artisan serve
Start in een apart terminalvenster Vite op om de CSS- en JavaScript-bestanden live te compileren:

Bash
npm run dev
Je kunt het portaal nu bezoeken via [http://127.0.0.1:8000](http://127.0.0.1:8000).

4. Rollen en Rechten
Het platform kent twee primaire gebruikersrollen die bepalen wat een gebruiker mag zien og doen:

Administrator (GKR Team)

Heeft toegang tot het volledige admin-dashboard (/admin/dashboard).

Kan nieuwe klanten, projecten en afspraakopties aanmaken en beheren.

Kan documenten uploaden en koppelen aan specifieke klantprojecten.

Klant (Client)

Heeft uitsluitend toegang tot de eigen klantomgeving.

Kan de status en details van het eigen project bekijken.

Kan gekoppelde documenten inzien/downloaden en feedback achterlaten via opmerkingen.

Kan zelfstandig afspraken inplannen op basis van de door de admin klaargezette opties.

5. Projectstructuur
De belangrijkste onderdelen van deze applicatie bevinden zich op de volgende plekken:

app/Models/: Bevat de databasemodellen en hun onderlinge relaties (User, Project, Document, Comment, Appointment, AppointmentOption).

app/Http/Controllers/: Bevat de logica van de schermen. Netjes opgesplitst in een Admin/ map voor de GKR-beheerder en een Client/ map voor de klantfuncties.

app/Http/Middleware/: Bevat beveiligingsfilters zoals IsAdmin (controleert of de gebruiker een administrator is) en SitePasswordProtection (voorziet de hele site van een algemeen toegangswachtwoord).

database/migrations/: De blauwdrukken van de database tabellen (inclusief tabellen voor projecten, documenten, opmerkingen en afspraken).

resources/views/: De visuele schermen van de applicatie, gebouwd met Blade-templates en gestyled met Tailwind CSS.

6. Deployment (Railway)
Dit project is voorbereid om eenvoudig te worden uitgerold via Railway.app.

In de map railway/ bevindt zich het script init-app.sh. Dit script zorgt ervoor dat tijdens het opstarten op het cloudplatform automatisch de juiste stappen worden gezet (zoals het optimaliseren van de configuratie en het veilig uitvoeren van database-migraties).

Zorg ervoor dat bij het instellen van de omgevingsvariabelen op Railway de database-koppeling correct naar de gekoppelde Railway database-dienst verwijst.
