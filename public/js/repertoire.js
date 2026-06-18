var esc = window.escapeHtml;

function loadWordCloud(retries) {
    if (retries === undefined) retries = 30;
    const cfg       = window.REPERTOIRE_CONFIG || {};
    const canvas    = document.getElementById('wordcloud');
    const container = document.getElementById('wordcloud-container');
    if (!canvas || !container) return;

    if (typeof WordCloud === 'undefined') {
        if (retries > 0) setTimeout(function () { loadWordCloud(retries - 1); }, 100);
        return;
    }

    fetch(cfg.wordcloudUrl)
        .then(response => response.json())
        .then(data => {
            if (!data || !data.length) return;
            const list = data.map(item => [item.text, item.size]);

            function render() {
                const w = container.offsetWidth;
                const h = container.offsetHeight;
                if (w === 0 || h === 0) { requestAnimationFrame(render); return; }

                const dpr = window.devicePixelRatio || 1;
                canvas.width  = w * dpr;
                canvas.height = h * dpr;

                WordCloud(canvas, {
                    list: list,
                    gridSize: 4,
                    weightFactor: function (size) {
                        return Math.pow(size, 0.7) * canvas.width / 70;
                    },
                    fontFamily: 'Arial, sans-serif',
                    fontWeight: 'bold',
                    color: function () {
                        const colors = ['#0d6efd','#6610f2','#6f42c1','#d63384','#dc3545',
                                        '#fd7e14','#ffc107','#198754','#20c997','#0dcaf0'];
                        return colors[Math.floor(Math.random() * colors.length)];
                    },
                    rotateRatio: 0.3,
                    rotationSteps: 2,
                    backgroundColor: '#f8f9fa',
                    drawOutOfBound: false,
                    shrinkToFit: true,
                    minSize: 8
                });
            }
            requestAnimationFrame(render);
        })
        .catch(error => {
            console.error('Error loading word cloud:', error);
            document.getElementById('wordcloud-container').innerHTML =
                '<div class="alert alert-info">Komponisten-Wolke wird geladen...</div>';
        });
}

function randomizeSongs(n) {
    if (n === undefined) n = 5;
    document.querySelectorAll('.style-songs[data-songs]').forEach(function (ul) {
        var songs;
        try { songs = JSON.parse(ul.dataset.songs); } catch (e) { return; }
        if (!songs.length) return;

        // Fisher-Yates shuffle then pick n
        var arr = songs.slice();
        for (var i = arr.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var tmp = arr[i]; arr[i] = arr[j]; arr[j] = tmp;
        }
        var pick = arr.slice(0, n);

        ul.innerHTML = pick.map(function (song) {
            var label = (song.composer ? esc(song.composer) + ' \u2013 ' : '') + esc(song.songName);
            return '<li><i class="bi bi-music-note me-1 text-muted"></i>' + label + '</li>';
        }).join('');
    });
}

pageLoad(function () { loadWordCloud(); randomizeSongs(); });
window.addEventListener('pageshow', function (event) {
    if (event.persisted) { loadWordCloud(); randomizeSongs(); }
});
