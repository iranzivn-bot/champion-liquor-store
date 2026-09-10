    </div> <!-- /.admin-content-wrapper -->
</div> <!-- /.admin-layout -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- JsBarcode -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

<!-- Admin JavaScript -->
<script src="<?= SITE_URL ?>assets/js/admin.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.barcode-display').forEach(function (el) {
        var barcode = el.getAttribute('data-barcode');
        var svg = el.querySelector('.barcode-svg');
        if (barcode && svg && typeof JsBarcode !== 'undefined') {
            try {
                JsBarcode(svg, barcode, {
                    format: 'CODE128',
                    width: 1,
                    height: 24,
                    displayValue: false,
                    background: 'transparent',
                    margin: 0
                });
            } catch (e) {}
        }
    });
});
</script>
</body>
</html>
