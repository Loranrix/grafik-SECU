# Problème Open Graph - Images Facebook

## 🚨 PROBLÈME IDENTIFIÉ
Quand on partage une URL de ciao.lv sur Facebook, aucune image ne s'affiche. Seulement le nom du site "ciao.lv" apparaît.

### URLs testées :
- `https://ciao.lv` (page d'accueil)
- `https://ciao.lv/ads/68f92c62` (annonce spécifique)

## 🔍 DIAGNOSTIC EFFECTUÉ

### Test avec Facebook Sharing Debugger :
- URL : https://developers.facebook.com/tools/debug/
- Erreur : "Propriété og:image doit être spécifiée de manière explicite"
- Erreur : "Erreur lors de la récupération du contenu"

### Test HTML généré :
```bash
curl -s "https://ciao.lv" | Select-String "og:"
```
**RÉSULTAT : AUCUNE balise Open Graph présente dans le HTML !**

Le HTML contient seulement :
- `<title>CIAO</title>`
- `<meta name="description" content="Plateforme de petites annonces multilingue">`

## 🛠️ TENTATIVES DE CORRECTION

### 1. Métadonnées dans layout.js ✅ (DÉJÀ PRÉSENT)
```javascript
export const metadata = {
  openGraph: {
    title: 'CIAO.LV - Plateforme de petites annonces multilingue',
    images: [{ url: 'https://via.placeholder.com/1200x630/1e40af/ffffff?text=CIAO.LV' }]
  }
}
```

### 2. Création page.js serveur ✅ (FAIT)
- Créé `app/page.js` avec métadonnées Open Graph
- Supprimé `app/page-simple.js` et `app/page-backup.js` (fichiers en conflit)

### 3. Balises HTML directes dans layout ❌ (ÉCHEC)
- Ajouté balises `<meta property="og:*">` dans le `<head>` du layout
- **RÉSULTAT : Balises non générées dans le HTML final**

### 4. Composant Head dans page.js ❌ (ÉCHEC)
- Tenté d'utiliser `import Head from 'next/head'`
- **ERREUR : Head ne fonctionne pas dans les composants serveur Next.js 13+**

### 5. Fichiers statiques créés (À SUPPRIMER) ❌
- `public/index.html` - fichier HTML statique avec balises OG
- `public/og-template.html` - template pour générer image manuellement
- `public/generate-og.html` - autre template
- `middleware.js` - middleware pour intercepter bots Facebook

### 6. Images Open Graph dynamiques ❌ (404)
- `app/opengraph-image.tsx` - génération d'image dynamique
- `app/ads/[id]/opengraph-image.tsx` - images pour annonces
- **PROBLÈME : URLs retournent 404**

## 🎯 PROBLÈME PRINCIPAL IDENTIFIÉ

**Next.js ne génère PAS les balises Open Graph dans le HTML statique malgré l'export `metadata` correct.**

Possible causes :
1. Configuration Next.js manquante
2. Problème avec le rendu serveur
3. Cache Next.js corrompu
4. Conflit entre composants client/serveur

## ⚠️ IMPORTANT - NE PAS CASSER LE CODE

**ATTENTION :** Toutes les fonctionnalités existantes du site doivent être préservées !
- Ne pas supprimer de composants fonctionnels
- Ne pas modifier la logique métier
- Ne pas toucher aux APIs existantes
- Garder toute la structure actuelle

## 🧹 NETTOYAGE À EFFECTUER

### Fichiers à supprimer (ajoutés pendant le debug) :
- `public/index.html`
- `public/og-template.html` 
- `public/generate-og.html`
- `middleware.js`

### Fichiers à garder :
- `app/layout.js` (avec métadonnées Open Graph)
- `app/page.js` (composant serveur)
- `app/opengraph-image.tsx` (si fonctionnel)
- `app/ads/[id]/layout.tsx` (métadonnées annonces)
- `app/ads/[id]/opengraph-image.tsx` (si fonctionnel)

## 🔄 PROCHAINES ÉTAPES

1. **Nettoyer les fichiers inutiles**
2. **Investiguer pourquoi Next.js ne génère pas les balises OG**
3. **Tester avec un projet Next.js minimal**
4. **Vérifier la configuration du serveur de production**
5. **Analyser les logs de build pour erreurs**

## 📊 ÉTAT ACTUEL

✅ **Métadonnées configurées** dans le code  
❌ **Balises non générées** dans le HTML  
❌ **Facebook ne voit aucune image**  
✅ **Site fonctionne normalement** (aucune fonctionnalité cassée)

---
*Dernière mise à jour : 21 novembre 2024*
*Problème en cours - À reprendre plus tard*