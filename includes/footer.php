        </div>
    </main>
    <footer class="main-footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> RWA Election App. All rights reserved.</p>
            <p>Made in India, by Ecologic</p>
        </div>
    </footer>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script>
        $(document).ready(function() {
            $('.responsive-table').DataTable({
                "paging": true,
                "searching": true,
                "ordering": true,
                "info": true
            });
        });
    </script>
    <script src="js/main.js"></script>
</body>
</html>