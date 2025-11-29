<?php
// includes/message.php
// This component displays success or error messages if they are set.

function display_messages($messages, $type) {
    if (empty($messages)) {
        return;
    }

    $role_attr = '';
    if ($type === 'error') {
        $role_attr = ' role="alert"';
    } elseif ($type === 'success') {
        $role_attr = ' role="status"';
    }

    if (is_array($messages)) {
        echo '<div class="' . htmlspecialchars($type) . '-messages">';
        foreach ($messages as $msg) {
            echo '<p class="' . htmlspecialchars($type) . '"' . $role_attr . '>' . htmlspecialchars($msg) . '</p>';
        }
        echo '</div>';
    } else {
        echo '<p class="' . htmlspecialchars($type) . '"' . $role_attr . '>' . htmlspecialchars($messages) . '</p>';
    }
}

// Check for array of errors or single error string
if (!empty($errors)) {
    display_messages($errors, 'error');
} elseif (!empty($error)) {
    display_messages($error, 'error');
}

// Check for array of success messages or single success string
if (!empty($success_messages)) {
    display_messages($success_messages, 'success');
} elseif (!empty($success)) {
    display_messages($success, 'success');
}
?>