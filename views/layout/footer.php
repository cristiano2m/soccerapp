<?php $layout = $layout ?? 'public'; ?>
<?php if ($layout === 'admin'): ?>
        </main>
    </div>
</div>
<?php else: ?>

<!-- ══ FOOTER PÚBLICO ══ -->
<footer class="lp-footer">
    <div class="lp-footer-top">
        <div class="container">
            <div class="lp-footer-grid">

                <!-- Columna 1: Marca -->
                <div class="lp-footer-col lp-footer-brand">
                    <img src="<?= BASE_URL ?>/assets/img/logoSoccerApp.png" alt="SoccerAPP" class="lp-footer-logo">
                    <p class="lp-footer-tagline">La plataforma digital para la gestión y seguimiento de torneos deportivos de tu comunidad.</p>
                    <div class="lp-footer-socials">
                        <!-- Agrega aquí tus redes sociales -->
                        <a href="#" class="lp-social" aria-label="Facebook">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                        </a>
                        <a href="#" class="lp-social" aria-label="Instagram">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                        </a>
                        <a href="#" class="lp-social" aria-label="WhatsApp">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
                        </a>
                    </div>
                </div>

                <!-- Columna 2: Torneos -->
                <div class="lp-footer-col">
                    <h4 class="lp-footer-heading">Torneos</h4>
                    <ul class="lp-footer-links">
                        <li><a href="<?= BASE_URL ?>/index.php">Todos los campeonatos</a></li>
                        <?php foreach (array_slice($torneosActivos ?? [], 0, 4) as $ft): ?>
                        <li><a href="<?= h(url_publica_torneo((int)$ft['id'])) ?>"><?= h($ft['nombre']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Columna 3: Navegación -->
                <div class="lp-footer-col">
                    <h4 class="lp-footer-heading">Plataforma</h4>
                    <ul class="lp-footer-links">
                        <li><a href="<?= BASE_URL ?>/index.php">Inicio</a></li>
                        <li><a href="<?= BASE_URL ?>/login.php">Acceso organizadores</a></li>
                        <?php if (is_logged_in()): ?>
                        <li><a href="<?= BASE_URL ?>/admin/dashboard.php">Panel Admin</a></li>
                        <li><a href="<?= BASE_URL ?>/auth/logout.php">Cerrar sesión</a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Columna 4: Contacto / Info -->
                <div class="lp-footer-col">
                    <h4 class="lp-footer-heading">Contacto</h4>
                    <ul class="lp-footer-info">
                        <li>
                            <span class="ms" style="font-size:16px;color:var(--color-primary);">location_on</span>
                            <!-- Cambia tu ciudad/dirección aquí -->
                            <span>Tu ciudad, País</span>
                        </li>
                        <li>
                            <span class="ms" style="font-size:16px;color:var(--color-primary);">email</span>
                            <!-- Cambia tu email aquí -->
                            <a href="mailto:info@soccerapp.com">info@soccerapp.com</a>
                        </li>
                        <li>
                            <span class="ms" style="font-size:16px;color:var(--color-primary);">phone</span>
                            <!-- Cambia tu teléfono aquí -->
                            <span>+1 (555) 000-0000</span>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </div>

    <div class="lp-footer-bottom">
        <div class="container">
            <span>&copy; <?= date('Y') ?> SoccerAPP · Todos los derechos reservados</span>
            <span>Hecho con <span class="ms" style="font-size:14px;color:var(--color-primary);vertical-align:-3px;">favorite</span> para el deporte</span>
        </div>
    </div>
</footer>

<?php endif; ?>
<script src="<?= BASE_URL ?>/assets/js/alpine.min.js" defer></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
