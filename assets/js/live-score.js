// SoccerAPP — Pantalla Live: polling cada 5 segundos
// Requiere un elemento <input type="hidden" id="partido-id" value="..."> en la página

(function () {
    const input = document.getElementById('partido-id');
    if (!input) return;

    const PARTIDO_ID = input.value;
    const POLL_INTERVAL = 5000;
    let pollingTimer = null;

    async function fetchLiveData() {
        try {
            const res = await fetch(`live-data.php?id=${PARTIDO_ID}`);
            const data = await res.json();
            if (data.success) {
                updateDisplay(data.data);
                if (data.data.estado === 'finalizado') {
                    clearInterval(pollingTimer);
                    const indicator = document.getElementById('live-indicator');
                    if (indicator) indicator.style.display = 'none';
                    const estadoLabel = document.getElementById('estado-label');
                    if (estadoLabel) estadoLabel.textContent = 'FINALIZADO';
                }
            }
        } catch (e) {
            console.error('Error en polling de pantalla live:', e);
        }
    }

    function updateDisplay(d) {
        const score = document.getElementById('score');
        if (score) score.textContent = `${d.local.goles} — ${d.visita.goles}`;

        const localName = document.getElementById('local-name');
        if (localName) localName.textContent = d.local.nombre;

        const visitaName = document.getElementById('visita-name');
        if (visitaName) visitaName.textContent = d.visita.nombre;

        const golesList = document.getElementById('goles-list');
        if (golesList) {
            golesList.innerHTML = d.goles.length
                ? d.goles.map(g => `<li>⚽ ${g.jugador} — min. ${g.minuto}' (${g.equipo === 'local' ? d.local.nombre : d.visita.nombre})</li>`).join('')
                : '<li class="text-muted">Sin goles registrados</li>';
        }

        const tarjetasList = document.getElementById('tarjetas-list');
        if (tarjetasList) {
            tarjetasList.innerHTML = d.tarjetas.length
                ? d.tarjetas.map(t => `<li>${t.tipo === 'roja' ? '🟥' : '🟨'} ${t.jugador} — min. ${t.minuto}'</li>`).join('')
                : '<li class="text-muted">Sin tarjetas</li>';
        }
    }

    pollingTimer = setInterval(fetchLiveData, POLL_INTERVAL);
    fetchLiveData();
})();
