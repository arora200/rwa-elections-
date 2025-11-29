# Contributing to Aanchal Vihar Election Application

We welcome contributions to the Aanchal Vihar Election Application! By following these guidelines, you can help us maintain a high-quality and collaborative environment.

## How to Contribute

1.  **Fork the Repository:** Start by forking the project repository to your GitHub account.
2.  **Clone Your Fork:** Clone your forked repository to your local machine.
    ```bash
    git clone https://github.com/YOUR_USERNAME/rwa-elections-.git
    cd rwa-elections-
    ```
3.  **Create a New Branch:** Create a new branch for your feature or bug fix.
    ```bash
    git checkout -b feature/your-feature-name-or-bugfix/issue-number
    ```
4.  **Set Up Development Environment:**
    *   Ensure you have PHP (with PDO SQLite extension) and a web server (or PHP's built-in server) installed.
    *   Set up the database: `php setup_db.php`
    *   (Optional but recommended for testing) Populate with dummy data: `php populate_full_dummy_data.php`
    *   Start the development server: `php -S localhost:8000`
5.  **Make Your Changes:** Implement your feature or fix the bug.
    *   Adhere to existing coding style and conventions.
    *   Write clear, concise, and well-documented code.
    *   Ensure all existing functionalities remain intact and new features work as expected.
6.  **Test Your Changes:**
    *   Thoroughly test your changes to ensure they do not introduce new bugs or break existing functionality.
    *   Verify responsiveness on different screen sizes.
    *   Test with different user roles where applicable.
7.  **Commit Your Changes:** Commit your changes with a clear and descriptive commit message.
    ```bash
    git commit -m "feat: Add new feature" # or "fix: Resolve bug in module"
    ```
8.  **Push to Your Fork:** Push your branch to your forked repository on GitHub.
    ```bash
    git push origin feature/your-feature-name-or-bugfix/issue-number
    ```
9.  **Create a Pull Request:**
    *   Go to the original repository on GitHub.
    *   You should see an option to create a Pull Request from your branch.
    *   Provide a detailed description of your changes, why they are needed, and how they have been tested.
    *   Link to any relevant issues.

## Coding Standards

*   **PHP:** Follow PSR-12 coding style guidelines.
*   **HTML/CSS/JS:** Maintain consistency with existing project structure and styling.
*   **Comments:** Add comments sparingly, focusing on *why* complex logic is implemented, not *what* it does.
*   **Database Interactions:** Use PDO prepared statements for all database operations to prevent SQL injection.

## Security Best Practices

*   **Sanitization & Validation:** Always sanitize user input and validate it before use.
*   **CSRF Protection:** Ensure all POST requests include CSRF tokens.
*   **Password Hashing:** Store passwords only as bcrypt hashes.

Thank you for your contributions!
