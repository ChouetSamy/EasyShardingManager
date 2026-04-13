# Ports de développement

## Pourquoi 8000 et 8001 ?

- 8000 → serveur Symfony local (hors Docker)
- 8001 → serveur Symfony dans Docker

## Objectif

Permettre :
- d’exécuter l’application en local (debug rapide)
- d’exécuter l’application dans Docker (environnement réaliste)

Sans conflit de port.

## Pourquoi c’est important ?

Dans une architecture microservices :

- chaque service tourne sur un port différent
- plusieurs instances peuvent coexister
- l’environnement Docker reflète la production

## Bonne pratique

Toujours éviter :
- de dépendre de localhost
- de hardcoder les ports

Préférer :
- variables d’environnement
- noms de services Docker