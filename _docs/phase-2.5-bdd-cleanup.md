# Phase 2.5 — Cleanup BDD commentaires

**Date** : 30 avril 2026
**Environnement** : Local (lamixtape.local)

## Backup pré-suppression

Localisation : /Users/n.rapp/dev/Backups/backup-comments-pre-phase-2.5.sql
Taille : 3736 octets
Tables : wp_comments + wp_commentmeta
Stockage : hors repo (cf. règle Phase 2.5)

## Compteurs

- wp_comments avant : 0 (Local n'a jamais eu de commentaires)
- wp_comments après : 0
- Posts avec comment_status=open avant : tous (~370+)
- Posts avec comment_status=closed après : tous

## Commandes WP-CLI exécutées

```bash
cd /Users/n.rapp/dev/Lamixtape/app/public

# 1. Compteur initial
wp comment list --format=count
# → 0

# 2. Backup
wp db export --tables=wp_comments,wp_commentmeta \
  /Users/n.rapp/dev/Backups/backup-comments-pre-phase-2.5.sql

# 3. Suppression commentaires (no-op vu count=0)
wp comment delete $(wp comment list --format=ids) --force \
  2>/dev/null || echo "no comments to delete"

# 4. Fermeture comment_status sur posts existants
#    (boucle bash, xargs échoue "command line too long" sur 370+ posts)
for id in $(wp post list --post_type=post --format=ids); do
  wp post update "$id" --comment_status=closed --ping_status=closed
done

# 5. Vérification
wp post list --post_type=post --field=comment_status --format=csv | sort -u
# → closed (uniquement)

# 6. Default WP options (déjà closed sur Local, no-op)
wp option update default_comment_status closed
wp option update default_ping_status closed
```

## Notes

- Sur Local, options default_*_status étaient déjà à "closed"
  (probablement réglage manuel antérieur ou WP par défaut récent).
- **À répliquer en prod** : exécuter la même séquence sur la prod
  quand le déploiement Phase 2.5 sera décidé. Vérifier en
  particulier la commande 4 (boucle bash, pas xargs) si la prod
  a aussi 370+ posts.
- Backup conservé hors repo, à supprimer manuellement après
  confirmation que la suppression est sans regret (ex. après
  déploiement prod réussi + 30 jours).
