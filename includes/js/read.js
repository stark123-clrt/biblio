/**
 * read.js - JavaScript pour l'interface de lecture PDF
 * Version POO - Créé le 08/11/2025
 */

// Configuration PDF.js
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

/**
 * Classe gérant le lecteur PDF
 */
class PDFReader {
    constructor(pdfPath, bookId, initialPage = 1) {
        this.pdfPath = pdfPath;
        this.bookId = bookId;
        this.pdfDoc = null;
        this.pageNum = initialPage;
        this.pageRendering = false;
        this.pageNumPending = null;
        this.scale = 1.0;
        this.canvas = document.getElementById('pdfCanvas');
        this.ctx = this.canvas.getContext('2d');
        this.isManualNavigation = false; // Flag pour détecter navigation manuelle
        
        this.init();
    }
    
    async init() {
        try {
            this.pdfDoc = await pdfjsLib.getDocument(this.pdfPath).promise;
            console.log('PDF chargé, page initiale:', this.pageNum);
            this.updatePageInfo();
            this.renderPage(this.pageNum);
            this.setupEventListeners();
        } catch (error) {
            console.error('Erreur lors du chargement du PDF:', error);
        }
    }
    
    setupEventListeners() {
        // Navigation des pages
        document.getElementById('prevPage').addEventListener('click', () => this.prevPage());
        document.getElementById('nextPage').addEventListener('click', () => {
            // Marquer explicitement la navigation comme manuelle
            this.isManualNavigation = true;
            this.nextPage();
        });
        
        // Zoom
        document.getElementById('zoomIn').addEventListener('click', () => this.zoomIn());
        document.getElementById('zoomOut').addEventListener('click', () => this.zoomOut());
        
        // Audio controls
        this.setupAudioControls();
        
        // Panneau latéral et onglets
        this.setupSidePanel();
        
        // Modal de notation
        this.setupRatingModal();
        
        // Navigation par les flèches du clavier
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') this.prevPage();
            if (e.key === 'ArrowRight') {
                // Navigation au clavier = manuelle
                this.isManualNavigation = true;
                this.nextPage();
            }
            if (e.key === '+' || e.key === '=') this.zoomIn();
            if (e.key === '-') this.zoomOut();
        });
        
        // Gestes tactiles
        this.setupTouchGestures();
    }
    
    setupAudioControls() {
        this.audioPlayer = document.getElementById('audioPlayer');
        this.isPlaying = false;
        this.isPaused = false;
        this.currentAudioUrl = null;
        this.audioChunks = [];
        this.currentChunkIndex = 0;
        this.currentPageText = '';
        this.preloadedAudios = new Map(); // Cache des audios préchargés
        this.isPreloading = false;
        this.continuousMode = false; // Mode lecture continue entre pages
        this.currentAudioPage = null; // Page en cours de lecture audio
        this.suppressAudioErrors = false; // Ne pas alerter lors des arrêts manuels/programmés
        // --- Préchargement de la page suivante ---
        this.nextPageNumberPreloaded = null; // Numéro de page préchargée (audio) si disponible
        this.nextPageText = '';
        this.nextPageChunks = [];
        this.nextPagePreloadedAudios = new Map(); // Map indexChunk -> audioUrl pour la page suivante
        this.isNextPagePreloading = false; // Flag de préchargement actif page suivante
        this.preloadThreshold = 2; // Quand il reste <= threshold chunks sur la page courante, on lance le préchargement de la suivante
        this.nextPagePreloadSpeed = null; // Vitesse utilisée lors du préchargement
        
        const playBtn = document.getElementById('playAudioBtn');
        const speedSelect = document.getElementById('audioSpeed');
        
        playBtn?.addEventListener('click', () => this.togglePlayPause());
        
        speedSelect?.addEventListener('change', (e) => {
            if (this.audioPlayer) {
                this.audioPlayer.playbackRate = parseFloat(e.target.value);
            }
        });
        
        // Événements du lecteur audio
        this.audioPlayer?.addEventListener('ended', () => {
            // Quand un chunk se termine, jouer le suivant
            this.playNextChunk();
        });
        
        this.audioPlayer?.addEventListener('error', (e) => {
            // Éviter les popups lors d'un arrêt manuel/programmée
            if (this.suppressAudioErrors) {
                console.log('🔇 Erreur audio ignorée (arrêt manuel/programmée)');
                return;
            }
            console.warn('⚠️ Erreur audio:', e);
            // Protéger contre boucle d'erreurs - pas d'alert
            this.suppressAudioErrors = true;
            this.pauseAudio();
            setTimeout(() => { this.suppressAudioErrors = false; }, 500);
        });
    }
    
    async togglePlayPause() {
        // Si on est en pause, reprendre OU recréer la page courante
        if (this.isPaused) {
            // Si la page a changé depuis la pause, recréer les chunks
            if (this.currentAudioPage !== this.pageNum) {
                console.log('🔄 Page changée, recréation des chunks...');
                await this.startPageAudio();
            } else {
                // Reprendre là où on était
                this.audioPlayer.play();
                this.isPaused = false;
                this.isPlaying = true;
                document.getElementById('playAudioBtn').innerHTML = '<i class="fas fa-pause"></i>';
            }
            return;
        }
        
        // Si déjà en lecture, mettre en pause
        if (this.isPlaying) {
            this.pauseAudio();
            return;
        }
        
        // Sinon, démarrer la lecture depuis le début de la page courante
        await this.startPageAudio();
    }
    
    pauseAudio() {
        if (this.audioPlayer && this.isPlaying) {
            this.audioPlayer.pause();
            this.isPlaying = false;
            this.isPaused = true;
            document.getElementById('playAudioBtn').innerHTML = '<i class="fas fa-play"></i>';
            console.log('⏸️ Audio en pause');
        }
    }
    
    async startPageAudio() {
        try {
            // Afficher l'indicateur de chargement
            document.getElementById('audioLoadingIndicator').style.display = 'block';
            document.getElementById('audioLoadingText').textContent = 'Extraction du texte...';
            
            // Activer le mode continu
            this.continuousMode = true;
            this.currentAudioPage = this.pageNum;
            
            // Extraire le texte de la page courante
            const page = await this.pdfDoc.getPage(this.pageNum);
            const textContent = await page.getTextContent();
            const pageText = textContent.items.map(item => item.str).join(' ').trim();
            
            if (!pageText || pageText.length < 10) {
                console.warn('⚠️ Aucun texte trouvé sur cette page');
                document.getElementById('audioLoadingIndicator').style.display = 'none';
                this.isPaused = false;
                this.isPlaying = false;
                return;
            }
            
            console.log(`📄 Texte extrait page ${this.pageNum}: ${pageText.length} caractères`);
            
            // Découper le texte en chunks intelligents (par phrases)
            this.currentPageText = pageText;
            this.audioChunks = this.splitTextIntoChunks(pageText, 1000);
            this.currentChunkIndex = 0;
            
            console.log(`✂️ ${this.audioChunks.length} chunks créés pour page ${this.pageNum}`);
            
            document.getElementById('audioLoadingText').textContent = `Génération audio (chunk 1/${this.audioChunks.length})...`;
            
            // Générer l'audio pour le premier chunk
            await this.playChunk(0);
            
        } catch (error) {
            console.error('❌ Erreur lors de la lecture audio:', error);
            document.getElementById('audioLoadingIndicator').style.display = 'none';
        }
    }
    
    splitTextIntoChunks(text, maxLength = 1000) {
        if (!text || text.trim().length === 0) return [''];
        
        text = text.trim();
        
        if (text.length <= maxLength) {
            return [text];
        }
        
        // 🧠 DÉCOUPAGE INTELLIGENT SUR PONCTUATION
        const chunks = [];
        let currentChunk = '';
        
        // Découper d'abord par phrases (comme l'ancienne version)
        const sentences = text.split(/([.!?]+\s+)/).filter(part => part.trim());
        
        for (let i = 0; i < sentences.length; i++) {
            const sentence = sentences[i];
            
            // Si ajouter cette phrase dépasse la limite ET qu'on a déjà du contenu
            if ((currentChunk + sentence).length > maxLength && currentChunk.length > 0) {
                chunks.push(currentChunk.trim());
                currentChunk = sentence;
            } else {
                currentChunk += sentence;
            }
        }
        
        // Ajouter le dernier chunk s'il reste quelque chose
        if (currentChunk.trim()) {
            chunks.push(currentChunk.trim());
        }
        
        console.log(`✂️ Découpage intelligent: ${chunks.length} chunks sur ponctuations`);
        return chunks;
    }
    
    async playChunk(chunkIndex) {
        if (chunkIndex >= this.audioChunks.length) {
            // Tous les chunks ont été joués
            this.stopAudio();
            return;
        }
        
        try {
            console.log(`🎵 Lecture chunk ${chunkIndex + 1}/${this.audioChunks.length}`);
            
            // 🚀 PRÉCHARGEMENT MASSIF DÈS LE PREMIER CHUNK
            if (chunkIndex === 0 && !this.isPreloading) {
                this.startMassivePreload();
            }
            
            let audioUrl = this.preloadedAudios.get(chunkIndex);
            
            if (!audioUrl) {
                // Si pas préchargé, générer maintenant
                console.log(`⚡ Chunk ${chunkIndex + 1} non préchargé, génération immédiate...`);
                document.getElementById('audioLoadingIndicator').style.display = 'block';
                document.getElementById('audioLoadingText').textContent = `Génération audio...`;
                document.getElementById('chunkProgress').textContent = `Chunk ${chunkIndex + 1}/${this.audioChunks.length}`;
                
                audioUrl = await this.generateAudioForChunk(chunkIndex);
                
                if (!audioUrl) {
                    throw new Error('Impossible de générer l\'audio pour ce chunk');
                }
            } else {
                console.log(`✅ Chunk ${chunkIndex + 1} déjà préchargé, lecture immédiate`);
            }
            
            const speed = parseFloat(document.getElementById('audioSpeed').value);
            
            // Charger et jouer l'audio
            this.audioPlayer.src = audioUrl;
            this.audioPlayer.playbackRate = speed;
            await this.audioPlayer.play();
            
            this.isPlaying = true;
            this.isPaused = false;
            this.currentChunkIndex = chunkIndex;
            this.currentAudioUrl = audioUrl;
            
            // Changer les boutons
            document.getElementById('playAudioBtn').innerHTML = '<i class="fas fa-pause"></i>';
            document.getElementById('playAudioBtn').style.display = 'inline-block';
            
            // Masquer l'indicateur de chargement
            document.getElementById('audioLoadingIndicator').style.display = 'none';

            // 🔮 Déclencher le préchargement de la page suivante si on approche de la fin
            const remaining = this.audioChunks.length - (chunkIndex + 1);
            if (this.continuousMode && remaining <= this.preloadThreshold && !this.isNextPagePreloading) {
                // Conditions: mode continu actif, pas déjà de préchargement et page suivante disponible
                if (this.pdfDoc && this.pageNum < this.pdfDoc.numPages && this.nextPageNumberPreloaded !== this.pageNum + 1) {
                    console.log(`🔮 Préchargement anticipé de la page ${this.pageNum + 1} (reste ${remaining} chunks sur la page courante)`);
                    this.preloadNextPage();
                }
            }
            
        } catch (error) {
            const msg = (error && error.message) ? error.message : String(error);
            // Ignorer les interruptions normales (ex: play() interrompu par pause)
            const isAbort = (error && (error.name === 'AbortError' || /interrupted|abort/i.test(msg)));
            if (!isAbort) {
                console.warn('⚠️ Lecture chunk interrompue:', msg);
            }
            document.getElementById('audioLoadingIndicator').style.display = 'none';
            // Pas d'alert - juste logger
            this.suppressAudioErrors = true;
            this.pauseAudio();
            setTimeout(() => { this.suppressAudioErrors = false; }, 500);
        }
    }
    
    async startMassivePreload() {
        if (this.isPreloading) return;
        
        this.isPreloading = true;
        console.log('🔄 Démarrage préchargement MASSIF en arrière-plan...');
        
        // Précharger TOUS les chunks suivants en parallèle (comme l'ancienne version)
        const preloadPromises = [];
        
        for (let i = 1; i < this.audioChunks.length; i++) {
            preloadPromises.push(this.preloadChunkSilently(i));
        }
        
        // Attendre tous les préchargements sans bloquer la lecture
        Promise.all(preloadPromises).then(() => {
            console.log(`✅ Préchargement COMPLET: ${this.audioChunks.length - 1} chunks prêts`);
            this.isPreloading = false;
        }).catch(error => {
            console.warn('⚠️ Erreur pendant le préchargement massif:', error);
            this.isPreloading = false;
        });
    }
    
    async preloadChunkSilently(index) {
        if (index >= this.audioChunks.length || this.preloadedAudios.has(index)) {
            return;
        }
        
        try {
            console.log(`📦 Préchargement silencieux chunk ${index + 1}/${this.audioChunks.length}...`);
            
            const audioUrl = await this.generateAudioForChunk(index);
            
            if (audioUrl) {
                this.preloadedAudios.set(index, audioUrl);
                console.log(`✅ Chunk ${index + 1} préchargé avec succès`);
            }
        } catch (error) {
            console.warn(`⚠️ Échec préchargement chunk ${index + 1}:`, error.message);
        }
    }
    
    async generateAudioForChunk(chunkIndex) {
        const chunkText = this.audioChunks[chunkIndex];
        const speed = parseFloat(document.getElementById('audioSpeed').value);
        
        const formData = new FormData();
        formData.append('text', chunkText);
        formData.append('speed', speed);
        formData.append('chunk_id', `page_${this.pageNum}_chunk_${chunkIndex}_${Date.now()}`);
        
        const response = await fetch('edge_tts.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status}`);
        }
        
        const responseText = await response.text();
        let result;
        
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('❌ Erreur parsing JSON:', responseText.substring(0, 200));
            throw new Error('Réponse serveur invalide');
        }
        
        if (result.success) {
            return result.audio_url;
        } else {
            throw new Error(result.error || 'Erreur de génération audio');
        }
    }

    // Génération audio générique pour un chunk arbitraire (hors page courante)
    async generateAudioForTextChunk(chunkText, pageNumber, chunkIndex, speedOverride = null) {
        const speed = speedOverride !== null ? speedOverride : parseFloat(document.getElementById('audioSpeed').value);
        const formData = new FormData();
        formData.append('text', chunkText);
        formData.append('speed', speed);
        formData.append('chunk_id', `page_${pageNumber}_chunk_${chunkIndex}_${Date.now()}`);
        const response = await fetch('edge_tts.php', { method: 'POST', body: formData });
        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status}`);
        }
        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('❌ Erreur parsing JSON (next page chunk):', responseText.substring(0, 200));
            throw new Error('Réponse serveur invalide');
        }
        if (!result.success) {
            throw new Error(result.error || 'Erreur génération audio (préchargement)');
        }
        return result.audio_url;
    }

    // Préchargement de la page suivante (texte + audio)
    async preloadNextPage() {
        if (this.isNextPagePreloading) return;
        if (!this.pdfDoc) return;
        const targetPage = this.pageNum + 1;
        if (targetPage > this.pdfDoc.numPages) return;
        this.isNextPagePreloading = true;
        this.nextPageNumberPreloaded = targetPage;
        this.nextPagePreloadedAudios = new Map();
        this.nextPageChunks = [];
        this.nextPageText = '';
        this.nextPagePreloadSpeed = parseFloat(document.getElementById('audioSpeed').value);
        console.log(`🚀 Début préchargement page ${targetPage} (mode anticipé)`);
        try {
            const page = await this.pdfDoc.getPage(targetPage);
            const textContent = await page.getTextContent();
            const pageText = textContent.items.map(item => item.str).join(' ').trim();
            if (!pageText || pageText.length < 10) {
                console.warn(`⚠️ Page ${targetPage} sans texte - préchargement annulé`);
                this.isNextPagePreloading = false;
                return;
            }
            this.nextPageText = pageText;
            this.nextPageChunks = this.splitTextIntoChunks(pageText, 1000);
            console.log(`✂️ ${this.nextPageChunks.length} chunks prévus pour page ${targetPage}`);
            // Précharger audio de chaque chunk en parallèle
            const promises = this.nextPageChunks.map((chunk, idx) => this.generateAudioForTextChunk(chunk, targetPage, idx, this.nextPagePreloadSpeed)
                .then(url => {
                    this.nextPagePreloadedAudios.set(idx, url);
                    console.log(`✅ Préchargé page ${targetPage} chunk ${idx + 1}/${this.nextPageChunks.length}`);
                })
                .catch(err => console.warn(`⚠️ Échec préchargement chunk ${idx + 1} page ${targetPage}: ${err.message}`))
            );
            await Promise.all(promises);
            console.log(`🎯 Page ${targetPage} entièrement préchargée (${this.nextPagePreloadedAudios.size}/${this.nextPageChunks.length} audios)`);
        } catch (error) {
            console.error('❌ Erreur préchargement page suivante:', error);
        } finally {
            this.isNextPagePreloading = false;
        }
    }

    clearNextPagePreloadCache() {
        this.nextPageNumberPreloaded = null;
        this.nextPageText = '';
        this.nextPageChunks = [];
        this.nextPagePreloadedAudios = new Map();
        this.isNextPagePreloading = false;
        this.nextPagePreloadSpeed = null;
    }
    
    async preloadChunk(index) {
        // Ancienne fonction gardée pour compatibilité mais non utilisée
        return this.preloadChunkSilently(index);
    }
    
    async playNextChunk() {
        const nextIndex = this.currentChunkIndex + 1;
        
        if (nextIndex < this.audioChunks.length) {
            // Il reste des chunks sur cette page
            console.log(`📍 Passage au chunk suivant: ${nextIndex + 1}/${this.audioChunks.length} (Page ${this.currentAudioPage})`);
            await this.playChunk(nextIndex);
        } else {
            // Tous les chunks de cette page sont terminés
            console.log(`✅ Page ${this.currentAudioPage} terminée`);
            
            if (this.continuousMode && this.pageNum < this.pdfDoc.numPages) {
                // Mode continu actif ET il reste des pages → passer à la page suivante
                console.log(`🔄 Passage automatique à la page ${this.pageNum + 1}`);
                await this.goToNextPageAndContinue();
            } else {
                // Fin du livre ou mode continu désactivé
                console.log('🛑 Fin de la lecture');
                this.stopAudio();
            }
        }
    }
    
    async goToNextPageAndContinue() {
        try {
            // Nettoyer les chunks de la page précédente
            this.audioChunks = [];
            this.currentChunkIndex = 0;
            this.preloadedAudios.clear();
            this.isPreloading = false;
            
            // Passer à la page suivante (automatiquement, pas manuellement)
            this.isManualNavigation = false;
            this.nextPage();
            
            // Attendre que la page soit rendue
            await this.waitForPageRender(this.pageNum);
            
            // Mettre à jour la page audio courante
            this.currentAudioPage = this.pageNum;
            
            console.log(`📄 Page ${this.pageNum} chargée, extraction du texte...`);
            
            // Extraire et découper le texte de la nouvelle page
            let pageText;
            // Réutiliser préchargement si disponible ET vitesse inchangée
            if (this.nextPageNumberPreloaded === this.pageNum && this.nextPageChunks.length > 0 && this.nextPagePreloadedAudios.size === this.nextPageChunks.length && this.nextPagePreloadSpeed === parseFloat(document.getElementById('audioSpeed').value)) {
                console.log(`🔁 Réutilisation du préchargement audio pour page ${this.pageNum}`);
                this.audioChunks = this.nextPageChunks.slice();
                this.preloadedAudios = this.nextPagePreloadedAudios; // Réutilise la map préchargée
                pageText = this.nextPageText;
                // Nettoyer le cache "page suivante" maintenant consommé
                this.clearNextPagePreloadCache();
            } else {
                const page = await this.pdfDoc.getPage(this.pageNum);
                const textContent = await page.getTextContent();
                pageText = textContent.items.map(item => item.str).join(' ').trim();
                if (!pageText || pageText.length < 10) {
                    console.warn(`⚠️ Page ${this.pageNum} sans texte, passage à la suivante...`);
                    if (this.pageNum < this.pdfDoc.numPages) {
                        await this.goToNextPageAndContinue();
                    } else {
                        this.stopAudio();
                    }
                    return;
                }
                this.audioChunks = this.splitTextIntoChunks(pageText, 1000);
                console.log(`✂️ ${this.audioChunks.length} chunks créés pour page ${this.pageNum}`);
            }
            
            // Jouer le premier chunk de la nouvelle page
            await this.playChunk(0);
            
        } catch (error) {
            console.error('❌ Erreur lors du passage à la page suivante:', error);
            this.stopAudio();
        }
    }
    
    async waitForPageRender(targetPage, maxWait = 3000) {
        console.log(`⏳ Attente rendu page ${targetPage}...`);
        const startTime = Date.now();
        
        return new Promise((resolve) => {
            const checkInterval = setInterval(() => {
                if (this.pageNum === targetPage && !this.pageRendering) {
                    clearInterval(checkInterval);
                    console.log(`✅ Page ${targetPage} rendue (${Date.now() - startTime}ms)`);
                    resolve();
                } else if (Date.now() - startTime > maxWait) {
                    clearInterval(checkInterval);
                    console.warn(`⚠️ Timeout attente page ${targetPage}`);
                    resolve();
                }
            }, 100);
        });
    }
    
    stopAudio() {
        if (this.audioPlayer) {
            this.audioPlayer.pause();
            this.audioPlayer.currentTime = 0;
            this.audioPlayer.src = '';
        }
        
        this.isPlaying = false;
        this.isPaused = false;
        this.audioChunks = [];
        this.currentChunkIndex = 0;
        this.currentPageText = '';
        this.preloadedAudios.clear(); // Vider le cache
        this.isPreloading = false;
        this.continuousMode = false; // Désactiver le mode continu
        this.currentAudioPage = null;
        this.clearNextPagePreloadCache();
        
        document.getElementById('playAudioBtn').style.display = 'inline-block';
        document.getElementById('playAudioBtn').innerHTML = '<i class="fas fa-play"></i>';
        document.getElementById('audioLoadingIndicator').style.display = 'none';
        
        // Nettoyer l'URL audio si nécessaire
        if (this.currentAudioUrl) {
            this.currentAudioUrl = null;
        }
    }
    
    setupSidePanel() {
        const toggleBtn = document.getElementById('toggleSidePanel');
        const closeBtn = document.getElementById('closeSidePanel');
        const sidePanel = document.querySelector('.side-panel');
        
        // Ouvrir/Fermer le panneau
        toggleBtn?.addEventListener('click', () => {
            sidePanel?.classList.toggle('active');
            if (sidePanel?.classList.contains('active')) {
                this.loadBookmarks();
                this.loadNotes();
            }
        });
        
        closeBtn?.addEventListener('click', () => {
            sidePanel?.classList.remove('active');
        });
        
        // Gestion des onglets
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const tabName = button.dataset.tab;
                
                // Désactiver tous les onglets
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // Activer l'onglet sélectionné
                button.classList.add('active');
                document.getElementById(tabName + 'Tab')?.classList.add('active');
                
                // Charger les données si nécessaire
                if (tabName === 'signets') {
                    this.loadBookmarks();
                } else if (tabName === 'notes') {
                    this.loadNotes();
                }
            });
        });
        
        // Ajouter un signet depuis le panneau
        document.getElementById('addBookmarkFromPanel')?.addEventListener('click', () => {
            this.saveBookmarkFromPanel();
        });
        
        // Ajouter une note depuis le panneau
        document.getElementById('addNoteFromPanel')?.addEventListener('click', () => {
            this.saveNoteFromPanel();
        });
        
        // Charger les signets au démarrage
        this.loadBookmarks();
    }
    
    saveBookmarkFromPanel() {
        const input = document.getElementById('bookmarkInput');
        const title = input.value.trim();
        
        if (!title) {
            alert('Veuillez entrer un nom pour le signet');
            return;
        }

        fetch('ajax/save_bookmark.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                book_id: this.bookId,
                page_number: this.pageNum,
                bookmark_name: title
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                this.loadBookmarks();
                alert('Signet enregistré avec succès');
            } else {
                alert('Erreur: ' + (data.message || 'Impossible d\'enregistrer le signet'));
            }
        })
        .catch(err => {
            console.error('Erreur:', err);
            alert('Erreur lors de l\'enregistrement du signet');
        });
    }
    
    saveNoteFromPanel() {
        const textarea = document.getElementById('noteInput');
        const content = textarea.value.trim();
        
        if (!content) {
            alert('Veuillez entrer une note');
            return;
        }

        fetch('ajax/save_note.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                book_id: this.bookId,
                page_number: this.pageNum,
                content: content
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                textarea.value = '';
                this.loadNotes();
                alert('Note enregistrée avec succès');
            } else {
                alert('Erreur: ' + (data.message || 'Impossible d\'enregistrer la note'));
            }
        })
        .catch(err => {
            console.error('Erreur:', err);
            alert('Erreur lors de l\'enregistrement de la note');
        });
    }
    
    setupRatingModal() {
        const rateBookBtn = document.getElementById('rateBookBtn');
        const ratingModal = document.getElementById('ratingModal');
        const closeRatingModal = ratingModal?.querySelector('.close-modal');
        const submitRatingBtn = document.getElementById('submitRatingBtn');
        const cancelRatingBtn = document.getElementById('cancelRatingBtn');
        const ratingStars = ratingModal?.querySelectorAll('.rating-stars i');
        
        // Ouverture manuelle
        rateBookBtn?.addEventListener('click', () => {
            ratingModal?.classList.remove('hidden');
        });
        
        // Ouverture automatique après 30 secondes
        setTimeout(() => {
            ratingModal?.classList.remove('hidden');
        }, 30000);
        
        this.selectedRating = 0;
        
        ratingStars?.forEach((star, index) => {
            star.addEventListener('click', () => {
                this.selectedRating = index + 1;
                ratingStars.forEach((s, i) => {
                    s.classList.toggle('active', i < this.selectedRating);
                });
                const ratingText = ratingModal.querySelector('.rating-text');
                ratingText.textContent = `Vous avez sélectionné ${this.selectedRating} étoile${this.selectedRating > 1 ? 's' : ''}`;
            });
        });
        
        closeRatingModal?.addEventListener('click', () => ratingModal?.classList.add('hidden'));
        cancelRatingBtn?.addEventListener('click', () => ratingModal?.classList.add('hidden'));
        submitRatingBtn?.addEventListener('click', () => this.saveRating(this.selectedRating));

        // Close modal on background click
        ratingModal?.addEventListener('click', (e) => {
            if (e.target === ratingModal) {
                ratingModal.classList.add('hidden');
            }
        });
    }
    
    saveRating(rating) {
        if (rating === 0) {
            alert('Veuillez sélectionner une note');
            return;
        }

        const comment = document.getElementById('ratingComment').value.trim();

        fetch('ajax/save_rating.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                book_id: this.bookId,
                rating: rating,
                comment_text: comment
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Note enregistrée avec succès');
                document.getElementById('ratingModal').classList.add('hidden');
                document.getElementById('ratingComment').value = '';
            } else {
                alert('Erreur: ' + (data.message || 'Impossible d\'enregistrer la note'));
            }
        })
        .catch(err => {
            console.error('Erreur:', err);
            alert('Erreur lors de l\'enregistrement de la note');
        });
    }
    
    loadNotes() {
        fetch(`ajax/get_notes.php?book_id=${this.bookId}`)
            .then(res => res.json())
            .then(data => {
                const notesList = document.querySelector('.notes-list');
                
                if (data.success && data.notes && data.notes.length > 0) {
                    notesList.innerHTML = data.notes.map(note => `
                        <div class="note-item" data-page="${note.page_number}">
                            <span class="note-page">Page ${note.page_number}</span>
                            <div class="note-content">${this.escapeHtml(note.content || note.note_text)}</div>
                        </div>
                    `).join('');
                    
                    // Add click listeners to notes
                    notesList.querySelectorAll('.note-item').forEach(item => {
                        item.addEventListener('click', () => {
                            const page = parseInt(item.dataset.page);
                            this.goToPage(page);
                        });
                    });
                } else {
                    notesList.innerHTML = `
                        <div style="text-align: center; padding: 40px 20px; color: #9ca3af;">
                            <i class="fas fa-sticky-note" style="font-size: 48px; color: #d1d5db; margin-bottom: 16px;"></i>
                            <p style="font-size: 16px; margin: 0;">Aucune note</p>
                            <p style="font-size: 14px; margin-top: 8px;">Ajoutez des notes pour vos réflexions</p>
                        </div>
                    `;
                }
            })
            .catch(err => {
                console.error('Erreur chargement notes:', err);
            });
    }

    loadBookmarks() {
        fetch(`ajax/get_bookmarks.php?book_id=${this.bookId}`)
            .then(res => res.json())
            .then(data => {
                const bookmarksList = document.querySelector('.bookmarks-list');
                
                if (data.success && data.bookmarks && data.bookmarks.length > 0) {
                    bookmarksList.innerHTML = data.bookmarks.map(bookmark => `
                        <div class="bookmark-item" data-page="${bookmark.page_number}">
                            <span class="bookmark-title">${this.escapeHtml(bookmark.bookmark_name)}</span>
                            <span class="bookmark-page">Page ${bookmark.page_number}</span>
                        </div>
                    `).join('');
                    
                    // Add click listeners to bookmarks
                    bookmarksList.querySelectorAll('.bookmark-item').forEach(item => {
                        item.addEventListener('click', () => {
                            const page = parseInt(item.dataset.page);
                            this.goToPage(page);
                            document.querySelector('.side-panel')?.classList.remove('active');
                        });
                    });
                } else {
                    bookmarksList.innerHTML = `
                        <div style="text-align: center; padding: 40px 20px; color: #9ca3af;">
                            <i class="fas fa-bookmark" style="font-size: 48px; color: #d1d5db; margin-bottom: 16px;"></i>
                            <p style="font-size: 16px; margin: 0;">Aucun signet</p>
                            <p style="font-size: 14px; margin-top: 8px;">Ajoutez des signets pour marquer vos pages</p>
                        </div>
                    `;
                }
            })
            .catch(err => {
                console.error('Erreur chargement marque-pages:', err);
                const bookmarksList = document.querySelector('.bookmarks-list');
                bookmarksList.innerHTML = `
                    <div style="text-align: center; padding: 40px 20px; color: #ef4444;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 16px;"></i>
                        <p>Erreur de chargement</p>
                    </div>
                `;
            });
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    setupTouchGestures() {
        let touchStartX = 0;
        let touchStartY = 0;
        
        this.canvas.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        }, { passive: true });
        
        this.canvas.addEventListener('touchend', (e) => {
            const touchEndX = e.changedTouches[0].clientX;
            const touchEndY = e.changedTouches[0].clientY;
            const deltaX = touchEndX - touchStartX;
            const deltaY = touchEndY - touchStartY;
            
            // Swipe horizontal (changement de page)
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 50) {
                if (deltaX > 0) {
                    this.isManualNavigation = true;
                    this.prevPage();
                } else {
                    this.isManualNavigation = true;
                    this.nextPage();
                }
            }
        }, { passive: true });
    }
    
    async renderPage(num) {
        if (this.pageRendering) {
            this.pageNumPending = num;
            return;
        }
        
        this.pageRendering = true;
        
        try {
            const page = await this.pdfDoc.getPage(num);
            const viewport = page.getViewport({ scale: this.scale });
            
            this.canvas.height = viewport.height;
            this.canvas.width = viewport.width;
            
            const renderContext = {
                canvasContext: this.ctx,
                viewport: viewport
            };
            
            await page.render(renderContext).promise;
            
            this.pageRendering = false;
            
            if (this.pageNumPending !== null) {
                this.renderPage(this.pageNumPending);
                this.pageNumPending = null;
            }
            
            // Sauvegarder la progression
            this.saveProgress(num);
            
        } catch (error) {
            console.error('Erreur lors du rendu de la page:', error);
            this.pageRendering = false;
        }
    }
    
    updatePageInfo() {
        const pageInfo = document.getElementById('pageInfo');
        if (this.pdfDoc) {
            pageInfo.textContent = `Page ${this.pageNum} / ${this.pdfDoc.numPages}`;
        }
    }
    
    prevPage() {
        if (this.pageNum <= 1) return;
        
        // Navigation MANUELLE → mettre audio en pause
        if (!this.isManualNavigation) {
            this.isManualNavigation = true;
        }
        
        if (this.isPlaying || this.isPaused) {
            console.log('⏸️ Navigation manuelle - Mise en pause');
            this.suppressAudioErrors = true;
            this.pauseAudio();
            setTimeout(() => { this.suppressAudioErrors = false; }, 500);
        }
        // Invalidation du préchargement future page (non pertinent après navigation manuelle)
        this.clearNextPagePreloadCache();
        
        this.pageNum--;
        this.renderPage(this.pageNum);
        this.updatePageInfo();
    }
    
    nextPage() {
        if (!this.pdfDoc || this.pageNum >= this.pdfDoc.numPages) return;
        
        // Si ce n'est PAS une navigation automatique (audio), c'est manuel
        if (this.isManualNavigation === false) {
            // C'est une navigation automatique de l'audio, ne rien faire
            this.pageNum++;
            this.renderPage(this.pageNum);
            this.updatePageInfo();
            return;
        }
        
        // Navigation MANUELLE → mettre audio en pause
        if (this.isPlaying || this.isPaused) {
            console.log('⏸️ Navigation manuelle - Mise en pause');
            this.suppressAudioErrors = true;
            this.pauseAudio();
            setTimeout(() => { this.suppressAudioErrors = false; }, 500);
        }
        this.clearNextPagePreloadCache();
        
        this.pageNum++;
        this.renderPage(this.pageNum);
        this.updatePageInfo();
    }
    
    goToPage(pageNum) {
        if (!this.pdfDoc || pageNum < 1 || pageNum > this.pdfDoc.numPages) return;
        
        // Navigation MANUELLE → mettre audio en pause
        if (this.isPlaying || this.isPaused) {
            console.log('⏸️ Navigation manuelle (goToPage) - Mise en pause');
            this.suppressAudioErrors = true;
            this.pauseAudio();
            setTimeout(() => { this.suppressAudioErrors = false; }, 500);
        }
        this.clearNextPagePreloadCache();
        
        this.isManualNavigation = true;
        this.pageNum = pageNum;
        this.renderPage(this.pageNum);
        this.updatePageInfo();
    }
    
    zoomIn() {
        this.scale *= 1.2;
        this.renderPage(this.pageNum);
    }
    
    zoomOut() {
        this.scale /= 1.2;
        this.renderPage(this.pageNum);
    }
    
    async saveProgress(page) {
        try {
            const response = await fetch('ajax/save_progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `book_id=${this.bookId}&page_number=${page}`
            });
            const data = await response.json();
        } catch (error) {
            console.error('Erreur lors de la sauvegarde de la progression:', error);
        }
    }
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    // Les variables pdfPath, bookId et initialPage sont injectées par PHP
    console.log('Initialisation - initialPage:', initialPage, 'bookId:', bookId);
    if (typeof pdfPath !== 'undefined' && typeof bookId !== 'undefined') {
        const startPage = (typeof initialPage !== 'undefined' && initialPage > 0) ? initialPage : 1;
        console.log('Création du lecteur avec page:', startPage);
        const reader = new PDFReader(pdfPath, bookId, startPage);
    }
});
