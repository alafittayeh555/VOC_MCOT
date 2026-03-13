<?php if (isset($_SESSION['user_id'])): ?>
    </main>
    <!-- MAIN -->
    </section>
    <!-- CONTENT -->
<?php else: ?>
    </div> <!-- End Guest Container -->
<?php endif; ?>

<script src="<?php echo isset($base_path) ? $base_path : ''; ?>assets/js/script.js?v=<?php echo time(); ?>"></script>
</body>

</html>