**Un dossier images a été créé pour voir les analyses qui ont été faites**

## 1. CE QUE J'AI PU REMARQUÉ EN PERF DANS LA PAGE /CAROUSEL

![Lighthouse Desktop](images-analyze/lighthouse-desktop.png)
![Lighthouse Mobile](images-analyze/lightouse-mobile.png)

Largest Contentful Paint (LCP) => 23s (desktop) / 137s (mobile)
Total Blocking Time (TBT) < 200 ms


- **Un chargement d'images massif et non optimisé :**
    - 230 images dans le dossier assets pour un total d'environ 900Mo
    - 147 fichiers JPG non convertis en WebP (format plus lourd), et jpg non compréssé ![Diagnostics Mobile](images-analyze/requests-img.png)
    - Aucun attribut width/height sur les img => provoque du CLS
    - Aucun alt => mauvais SEO
    - Aucun lazy-loading => toutes les images sont chargés d'un coup
    - aucun preload pour l'img du LCP
    - le payload total est d'environ 700Mo => du aux images (![Diagnostics Mobile](images-analyze/devtools-networks.png))
    - On peut voir que ce charge après 3 secondes sont a 100% des images (![Diagnostics Mobile](images-analyze/charge-after-3s.png))
    -     


![Diagnostics Mobile](images-analyze/head-dom.png)
- **Absence de SEO :**
    - Pas de balsie META
    - Title générique
    - pas de meta viewport pour mobile
    - Aucune balise sémantique : article, nav, header, footer...
    - pas de robot.txt ni de sitemap
    - pas de Schema Markup (json-ld) pour les produits


![Diagnostics Mobile](images-analyze/diagnostics-mobile.png)
- **Front - CSS/JS :**
    - CSS non minifié
    - JS non minifie
    - JS unitilisé
    - Render-blocking sur le rendu
    - Aucun preload pour le CSS critique
    - Le thread de main est a environ 7,5s (le /carousel est appelé 16 fois)


![Diagnostics Mobile](images-analyze/nocache-storage.png)
- **Front pas de cache nav :**
    - aucun header cache-control dans les assets
    - chaque rechargement de passe, toutes les ressources sont retéléchargés


- **Back Problème requête :**
    - le controller Carousel exécute les requêtes en boucle : foreach sur galaxy + foreach sur modeleFiles
    - On pourrait avoir seulement 1 requête SQL, au lieu de ça on boucle sur les fichiers, ce qui peut causer un énorme ralentissement dans le LCP


- **Back pas de cache serveur :**
    - Aucune mise en cache des données serveur (CacheInterface) => sans ajouts de headers de cache (rappel de cache-control, fichier CarouselController ![Diagnostics Mobile](images-analyze/devtools-networks.png))
    - Les données sont recalculées à chaque requêtes HTTP


- **INFRA serveur :**
    - Dans le readme on peut lire que le serveur est situé au canada, avec des resources plutôt limitées + pas d'accès au serveur



## 2. SOLUTIONS PROPOSÉES POUR LA PAGE /CAROUSEL

- **Solutions FRONT — Images et chargement :**
    - Utiliser le WebP a la place du jpg (convertir et compression en webp avec iloveimg)
    - Changement dans le img du fichier index.html.twig pour remplacer l'extention de l'image JPG par webp
    - Mise en place d'une pagination pour limiter le chargement des images
    - Ajouter loading="lazy" sur les images hors viewport pour réduire le LCP et le payload initial
    - Ajouter width et `height sur les <img> pour éviter le CLS
    - Ajouter fetchpriority="high" sur l'image LCP pour prioriser son chargement
    - Ajouter les attributs alt sur toutes les images (SEO et accessibilité)

- **Solutions FRONT — SEO et structure :**
    - Corriger le <title> et ajouter <meta description> pour l'indexation
    - Ajouter la balise <meta viewport> pour le mobile
    - Utiliser des balises sémantiques HTML5 (article, nav, header, footer...)
    - Construire et exposer un fichier robots.txt pour autoriser / guider l'indexation
    - Construire et exposer un sitemap.xml pour l'organisation des pages (génération manuelle ou via bundle Symfony)
    - Ajouter un Schema Markup JSON-LD (schema.org) pour contextualiser les données et améliorer l'affichage dans les résultats de recherche

- **Solutions FRONT — CSS / JS :**
    - Différer le JS non critique en déplaçant le bloc js en bas du body
    - Ajouter un preload pour le CSS critique afin de prioriser son chargement sans bloquer le rendu
    - Minifier le CSS Tailwind en production via la commande php bin/console tailwind:build --minify
    - Minifier et compiler les assets JS en production via php bin/console asset-map:compile

- **Solutions BACK :**
    - $response->setPublic() + $response->setMaxAge(3600) — la page est mise en cache 1h côté navigateur, plus besoin de re-requêter le serveur à
    chaque visite
    - count($galaxyRepository->findAll()) remplacé par $galaxyRepository->count([]) — fait un SELECT COUNT(*) au lieu de charger toutes les entités
    en mémoire juste pour les compter
    - Ajouter un cache HTTP sur la réponse (Cache-Control) pour que les pages et assets soient servis en cache navigateur
    - Paginer les résultats ou limiter le nombre d'items affichés pour réduire le payload
    - Échapper correctement les données (escape au lieu de raw) pour éviter les failles XSS


## 3. Résultats après optimisation
On voit tout de même une différence avec le lighthouse de base, on passe de 23s a 4 avec un passage a 78
On voit également du webp partout, un memory cache, des ressources très résonnable par rapport a avant
Des images qui chargent au fur et a mesure du scroll
Le lighthouse du mobile est également bien mieux, on passe d'un TBT de 1,7s a 260ms, et d'un LCP a 137s a 17s. Ce n'est pas a négligé
En lcp on passe en mobile de 48 a 70

## 4. Conclusion et améliorations futures

**Améliorations futures :**
- Mise en place d’un CDN pour distribuer les assets (images, CSS, JS) et réduire la latence (serveur au Canada → visiteurs européens)
- Cache serveur (ex. Redis) pour les données ou les réponses, en restant léger (VPS 2 Go RAM)
- Compression GZIP ou Brotli côté serveur sur les réponses texte (HTML, CSS, JS, JSON)
- Scaling horizontal (plusieurs instances, répartition de charge), si le trafic augmente