# Høstmessa

Nettside for Høstmessa, som arrangeres av Lindesnes KFUK-KFUM hver høst.

- **Forside** (`/`): informasjon om årets messe og noen smakebiter fra auksjonen.
- **Auksjon** (`/auksjon/`): katalog over varene som selges på auksjonen, med flere bilder per vare, søk og kategorifilter, og et bildegalleri for varer som ikke er beskrevet i katalogen.
- **Basar** (`/basar/`): informasjon om basaren.
- **Historie** (`/historie/`): messens historie.
- **For komiteen** (`/komite/`): innlogging og egne undersider for utvalgte varer (med beskjæring av hovedbildet), bildegalleri, kategorier, messeinfo, sidetekster, brukere og eget passord. Et galleribilde kan gjøres om til en utvalgt vare. Det finnes to brukergrupper: **administratorer** kan endre alt, mens **bidragsytere** kan legge ut varer og galleribilder og endre det de har lagt ut selv. Brukere som fantes før brukergruppene kom, er administratorer.

Løsningen kjører som tre Docker-containere:

| Container | Hva den gjør |
|---|---|
| `web` | PHP 8.3 med Apache. Selve nettsiden. |
| `db` | MariaDB 11.4. Varer, komitémedlemmer og innloggingsforsøk. |
| `tunnel` | Cloudflare Tunnel. Gjør siden tilgjengelig på høstmessa.no med HTTPS uten å åpne porter i ruteren. |

Bilder og database lagres i mappen `data/` ved siden av `docker-compose.yml`.

## Oppsett på Synology NAS

Krever DSM 7.2 eller nyere og en modell som støtter Container Manager (for eksempel DS220+).

### 1. Installer Container Manager

Åpne **Pakkesenter**, søk etter **Container Manager** og installer den.

### 2. Legg filene på NAS-en

1. Last ned repoet som ZIP fra GitHub (**Code → Download ZIP**) og pakk det ut.
2. Lag mappen `docker/hostmessa` i **File Station** og last opp innholdet dit, slik at `docker-compose.yml` ligger direkte i `docker/hostmessa`.
3. Lag mappen `data` inne i `docker/hostmessa`, og inne i den de tomme mappene `db` og `uploads`. Container Manager lager ikke disse selv, og uten dem stopper oppstarten med en feil om at mappen ikke finnes.

### 3. Lag innstillingsfilen `.env`

Kopier `.env.example` til `.env` i samme mappe og fyll inn verdiene. Filen kan redigeres med pakken **Text Editor**.

- `DB_PASSWORD` og `DB_ROOT_PASSWORD`: lange, tilfeldige passord. Du trenger ikke huske dem.
- `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`: det første komitémedlemmet. Brukeren opprettes automatisk første gang siden åpnes. Logg inn og bytt passord etterpå.
- `MESSE_*`, `AUKSJON_TID`, `VAREFRIST`, `KONTAKT`: startverdier for messeinfoen. Når komiteen har lagret messeinfo på komitésiden, brukes den i stedet.
- `CLOUDFLARE_TUNNEL_TOKEN`: se neste steg.

`.env` inneholder passord og skal aldri legges i Git.

### 4. Sett opp Cloudflare Tunnel

1. Lag en gratis konto på [cloudflare.com](https://dash.cloudflare.com) og legg til domenet. Høstmessa.no skrives `xn--hstmessa-54a.no` i DNS-systemet, og det er den formen Cloudflare kan be om.
2. Bytt navnetjenere hos domeneregistraren til de to Cloudflare oppgir. Det kan ta noen timer før det slår gjennom.
3. Gå til **Zero Trust → Networks → Tunnels → Create a tunnel**, velg **Cloudflared**, gi den et navn og kopier tokenet (den lange teksten etter `--token`) inn i `CLOUDFLARE_TUNNEL_TOKEN` i `.env`.
4. Under **Public Hostname**: velg domenet, la subdomenet stå tomt, og sett **Service** til `http://web:80` (ikke `localhost`, som Cloudflare foreslår). Cloudflare lager da DNS-oppføringen for domenet automatisk.

### 5. Start prosjektet

1. Åpne **Container Manager → Prosjekt → Opprett**.
2. Gi prosjektet navnet `hostmessa`, velg mappen `docker/hostmessa` og velg å bruke den eksisterende `docker-compose.yml`.
3. Fullfør veiviseren. Første gang bygges nettsiden, og det tar noen minutter.

Siden er nå tilgjengelig på hjemmenettet på `http://<NAS-ens IP>:8080` og, når tunnelen er oppe, på https://høstmessa.no.

### 6. Første innlogging

Gå til `/komite/`, logg inn med `ADMIN_EMAIL` og `ADMIN_PASSWORD`, og bytt passord nederst på siden. Legg deretter til resten av komiteen under **Brukere**.

### 7. E-post (glemt passord og nye medlemmer)

Nettsiden sender e-post via Brevo (eller en annen SMTP-tjeneste) når noen har glemt passordet, og når et nytt komitémedlem legges til uten passord.

1. I Brevo: gå til **Senders, Domains & Dedicated IPs → Domains**, legg til domenet (`xn--hstmessa-54a.no`) og legg inn DNS-oppføringene Brevo viser (DKIM, Brevo-kode og DMARC) under **DNS** i Cloudflare. Velg **DNS only** (grå sky) på dem. Legg så til avsenderadressen under **Senders**.
2. Gå til **SMTP & API → SMTP**. Der står SMTP-innloggingen (en adresse som slutter på `@smtp-brevo.com`). Lag en SMTP-nøkkel med **Generate a new SMTP key**.
3. Fyll inn i `.env`:
   - `SMTP_HOST=smtp-relay.brevo.com` og `SMTP_PORT=587`
   - `SMTP_USER`: SMTP-innloggingen fra Brevo
   - `SMTP_PASSWORD`: SMTP-nøkkelen
   - `MAIL_FROM`: avsenderadressen du la til i Brevo
4. Stopp og start prosjektet i Container Manager, slik at de nye innstillingene leses inn.
5. Logg inn på `/komite/` og trykk **Send test-e-post til meg** under Brukere.

Når e-post er satt opp, vises **Glemt passord?** på innloggingssiden. Lenken i e-posten virker i 1 time. Legger du til et medlem og lar passordfeltet stå tomt, får medlemmet en e-post og lager passordet selv (lenken virker i 7 dager).

## Backup

Alt som må tas vare på ligger i `docker/hostmessa/data`. Databasefiler som kopieres mens databasen kjører, kan bli ødelagte, så ta en databasedump før backup. Legg inn en planlagt oppgave i **Kontrollpanel → Oppgaveplanlegger → Opprett → Planlagt oppgave → Brukerdefinert skript**, kjørt som `root` hver natt:

```sh
cd /volume1/docker/hostmessa && \
/usr/local/bin/docker compose exec -T db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" hostmessa' > data/hostmessa.sql
```

Ta så backup av `docker/hostmessa/data` med **Hyper Backup**. Juster `/volume1` hvis mappen ligger på et annet volum.

## Oppdatere nettsiden

Koden i `public/` og `src/` leses rett fra NAS-mappen. Last opp de endrede filene til `docker/hostmessa` i File Station (ikke overskriv `.env` eller `data/`), og last siden på nytt i nettleseren. Det trengs ingen ny bygging.

Bare endringer i `Dockerfile`, `docker/` eller `docker-compose.yml` krever at du velger **Bygg** på prosjektet i Container Manager. Varer, bilder og tekster ligger i `data/` og blir værende.

Nye tabeller i databasen opprettes automatisk første gang siden lastes etter en oppdatering.

## Utvikling

Siden kjøres lokalt med Docker:

```sh
cp .env.example .env   # fyll inn verdier
docker compose up --build web db
```

Åpne http://localhost:8080. Tunnel-containeren trengs ikke lokalt.

Struktur:

- `public/`: det Apache serverer. `index.php` er forsiden, `auksjon/`, `basar/`, `historie/` og `komite/` er de andre sidene.
- `src/`: delt PHP-kode (database, innlogging, bildehåndtering), sidemal og `komite.php` med alt komitésidene lagrer.
- `db/init.sql`: tabellene. Kjøres automatisk første gang databasen opprettes.
- `docker/`: konfigurasjon for PHP og Apache.

Sikkerhet: passord lagres med `password_hash`, alle skjemaer har CSRF-token, alle databasekall bruker prepared statements, og innlogging sperres i 15 minutter etter 5 feil forsøk på samme e-post. Opplastede bilder leses inn og lagres på nytt som JPEG (uten metadata, maks 1600 px), og Apache kjører aldri kode fra bildemappen.
