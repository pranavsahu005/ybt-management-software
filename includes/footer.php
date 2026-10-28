<?php
/**
 * YBT Management Software — Page Footer
 * Closes layouts and loads scripts
 */
if (!defined('YBT_ROOT')) exit;

$isPublicPage = $isPublicPage ?? false;
?>
<?php if (!$isPublicPage): ?>
            </div><!-- /.content-container -->
        </main><!-- /.main-content -->

        <footer class="app-footer">
            <div class="footer-left">
                <span>&copy; <?= date('Y') ?> <strong><?= e(APP_NAME) ?></strong> &bull; Co. Pranav Raj Sahu (Hexatom Team / Aronar AI). All rights reserved.</span>
            </div>
            <div class="footer-right">
                <span class="text-muted">v<?= e(APP_VERSION) ?></span>
                <span class="text-separator">•</span>
                <a href="<?= url('docs/user-manual.md') ?>" target="_blank" class="text-muted">Help & Docs</a>
            </div>
        </footer>
    </div><!-- /.main-wrapper -->
</div><!-- /.app-shell -->
<?php endif; ?>

<!-- Chart.js for visualizations (Local with CDN fallback) -->
<script src="<?= asset('js/chart.umd.min.js') ?>"></script>
<script>if (!window.Chart) { document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"><\/script>'); }</script>

<!-- Application Scripts -->
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/tables.js') ?>"></script>

<!-- Page Specific Scripts -->
<?php if (!empty($pageScripts) && is_array($pageScripts)): ?>
    <?php foreach ($pageScripts as $script): ?>
        <script src="<?= asset('js/' . ltrim($script, '/')) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
