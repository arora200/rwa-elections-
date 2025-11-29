        </div>
    </main>
    <footer class="main-footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> RWA Election App. All rights reserved.</p>
            <p>Made in India, by Ecologic</p>
            <div class="social-links">
                <a href="https://aanchalvihar.blogspot.com/" target="_blank" rel="noopener noreferrer">Blog</a> |
                <a href="https://www.youtube.com/@Aanchalvihar" target="_blank" rel="noopener noreferrer">YouTube</a> |
                <a href="https://x.com/aanchalvihar2" target="_blank" rel="noopener noreferrer">X (Twitter)</a> |
                <a href="https://wa.me/918950370680" target="_blank" rel="noopener noreferrer">WhatsApp</a>
            </div>

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