# Mise en production OVH - oz.webmove.fr

Le domaine public de la webapp et de l'API est :

```text
https://oz.webmove.fr
```

L'API mobile utilise le meme domaine :

```text
https://oz.webmove.fr/api/login
https://oz.webmove.fr/api/me
https://oz.webmove.fr/api/outings
```

## DNS OVH

La zone DNS doit pointer vers le VPS :

```text
oz.webmove.fr.  A     54.36.182.92
oz.webmove.fr.  AAAA  2001:41d0:305:2100::9818
```

## Variables de production

Sur le VPS, creer un fichier non versionne :

```bash
cp .env.prod.example .env.prod.local
```

Puis remplacer toutes les valeurs `CHANGE_ME_*`.

Generer les secrets :

```bash
openssl rand -hex 32
```

Generer les hashes des comptes direction :

```bash
docker compose --env-file .env.prod.local -f compose.yaml -f compose.prod.yaml run --rm php bin/console security:hash-password
```

## Demarrage production

```bash
docker compose --env-file .env.prod.local -f compose.yaml -f compose.prod.yaml up -d --build --wait
```

## Verification rapide

```bash
curl -I https://oz.webmove.fr
curl -I https://oz.webmove.fr/api/me
```

`/api/me` doit repondre en erreur d'authentification si aucun token mobile n'est fourni. C'est normal.

## App mobile Flutter

L'app mobile utilise par defaut :

```text
https://oz.webmove.fr
```

Pour forcer une autre URL en developpement :

```bash
flutter run --dart-define=API_BASE_URL=http://ADRESSE_IP_LOCALE
```
