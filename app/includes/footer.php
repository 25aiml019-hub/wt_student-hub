<?php
/**
 * Shared Footer Component
 * StudentHub - CSPIT, CHARUSAT
 */

if (!isset($basePath)) {
    $basePath = '';
}
$assetPath = ($basePath === '../') ? '../public/' : $basePath;
?>
    <footer class="site-footer">
        <p class="footer-text">
            &copy; 2026 StudentHub | Designed &amp; Developed by 
            <strong>Daksh</strong> (25AIML019, CSPIT CHARUSAT)
        </p>
    </footer>

    <!-- Global Interactive Script -->
    <script src="<?= $assetPath ?>assets/js/main.js"></script>
    <?php if (isset($extraJs)): ?>
        <?php foreach ((array)$extraJs as $jsFile): ?>
            <script src="<?= $assetPath ?>assets/js/<?= htmlspecialchars($jsFile) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
