# Klantportaal-GKR

## 🪟 Windows Setup (Laravel Herd)

Volg deze stappen als je dit project voor het eerst opstart op een Windows-computer met Laravel Herd.

### 1. Systeem Vereisten

1. Download en installeer **[Laravel Herd voor Windows](https://herd.laravel.com)**.
2. Download en installeer **[Node.js](https://nodejs.org)** (voor npm).

### 2. Project Koppelen aan Herd

Open je terminal in de hoofdmap van het project en voer uit:

```bash
cd ..
herd park
cd Klantportaal-GKR

Het project is nu lokaal bereikbaar via: http://klantportaal-gkr.test

. Configuratie & Database
Draai de volgende commando's in de projectmap om de backend te initialiseren:

Bash
# Maak de omgevingsvariabelen aan
cp .env.example .env

# Genereer de unieke beveiligingssleutel
php artisan key:generate

# Bouw de SQLite database op (typ 'yes' bij de pop-up)
php artisan migrate
4. Frontend & Vite (Styling)
Om de Tailwind CSS en JavaScript live te compileren op Windows, gebruik je:

Bash
# Installeer de pakketjes
npm install

# Start de Vite server
npm run dev
5. Website Openen
Ga in je browser naar: http://klantportaal-gkr.test

Laadt de styling niet direct? Gebruik Ctrl + F5 voor een harde refresh.


---

Kort, strak en precies wat een andere developer (of je beoordelaar) nodig heeft om zonder errors binnen te komen.

Nu dit staat en Hanly mee kan kijken: zullen we de tanden gaan zetten in de **Feedb
```
