<?php
// edit_presentations.php
session_start(); // VERY IMPORTANT: Start the session at the beginning

// --- Authentication Check ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php'); // Redirect to login page if not logged in
    exit();
}
// --- Configuration ---
$playerHtmlFile = 'Player.html'; // Path to your player.html file
$message = '';
$message_type = ''; // 'success' or 'error'

// --- Function to read and parse the presentations array from player.html ---
function readPresentationsFromHtml($filePath) {
    if (!file_exists($filePath)) {
        return ['error' => 'player.html not found.'];
    }
    $htmlContent = file_get_contents($filePath);

    // Regex to find the 'const presentations = [...];' block
    // The '/s' modifier makes '.' match newlines
    $regex = '/const\s+presentations\s*=\s*(\[[^\]]*?\]);/s';

    if (preg_match($regex, $htmlContent, $matches)) {
        $jsonString = $matches[1]; // Get the content of the array
        $presentations = json_decode($jsonString, true); // Decode as associative array

        if (json_last_error() === JSON_ERROR_NONE) {
            return ['data' => $presentations, 'original_js_block' => $matches[0]];
        } else {
            return ['error' => 'Failed to parse JSON from player.html: ' . json_last_error_msg()];
        }
    } else {
        return ['error' => 'Could not find "const presentations = [...];" block in player.html. Ensure its format is consistent.'];
    }
}

// --- Function to write the updated presentations array back to player.html ---
function writePresentationsToHtml($filePath, $oldJsBlock, $newPresentationsArray) {
    if (!file_exists($filePath)) {
        return ['error' => 'player.html not found. Cannot write.'];
    }
    if (!is_writable($filePath)) {
        return ['error' => 'player.html is not writable. Please check file permissions.'];
    }

    $htmlContent = file_get_contents($filePath);

    // Prepare the new JavaScript array string
    // JSON_PRETTY_PRINT makes it nicely formatted for readability in HTML
    $newJsonString = json_encode($newPresentationsArray, JSON_PRETTY_PRINT);
    $newJsBlock = "const presentations = " . $newJsonString . ";";

    // Replace the old JavaScript block with the new one
    $updatedHtmlContent = str_replace($oldJsBlock, $newJsBlock, $htmlContent);

    if (file_put_contents($filePath, $updatedHtmlContent) !== false) {
        return ['success' => true];
    } else {
        return ['error' => 'Failed to write updated content to player.html.'];
    }
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['presentations'])) {
    $readResult = readPresentationsFromHtml($playerHtmlFile);

    if (isset($readResult['error'])) {
        $message = $readResult['error'];
        $message_type = 'error';
    } else {
        $oldJsBlock = $readResult['original_js_block'];
        $updatedPresentations = [];

        // Loop through submitted data to reconstruct the array
        // Ensure that 'url' and 'duration' arrays are present and of same length
        if (isset($_POST['presentations']['url']) && isset($_POST['presentations']['duration']) &&
            is_array($_POST['presentations']['url']) && is_array($_POST['presentations']['duration']) &&
            count($_POST['presentations']['url']) === count($_POST['presentations']['duration'])) {

            foreach ($_POST['presentations']['url'] as $index => $url) {
                $duration = (int)$_POST['presentations']['duration'][$index];
                $updatedPresentations[] = [
                    'url' => htmlspecialchars($url), // Sanitize input
                    'duration' => $duration,
                    'originalDuration' => $duration // Assuming originalDuration is same as duration
                ];
            }

            // Write updated data back to player.html
            $writeResult = writePresentationsToHtml($playerHtmlFile, $oldJsBlock, $updatedPresentations);

            if (isset($writeResult['success'])) {
                $message = 'Presentations updated successfully!';
                $message_type = 'success';
                // Redirect to self to clear POST data and show message
                header('Location: edit_presentations.php?status=success');
                exit();
            } else {
                $message = $writeResult['error'];
                $message_type = 'error';
                // Redirect to self to clear POST data and show message
                header('Location: edit_presentations.php?status=error&msg=' . urlencode($writeResult['error']));
                exit();
            }
        } else {
            $message = 'Invalid form data received. Please ensure all fields are properly submitted.';
            $message_type = 'error';
        }
    }
}

// --- Display initial status message after redirect ---
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $message = 'Presentations updated successfully!';
        $message_type = 'success';
    } elseif ($_GET['status'] === 'error') {
        $message = $_GET['msg'] ?? 'An error occurred during save.';
        $message_type = 'error';
    }
}


// --- Read Presentations for Display (either initial load or after failed save) ---
$presentationsData = [];
$readResult = readPresentationsFromHtml($playerHtmlFile);
if (isset($readResult['error'])) {
    $message = $readResult['error'];
    $message_type = 'error';
} else {
    $presentationsData = $readResult['data'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Presentations</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container {
            max-width: 800px;
            margin: 30px auto;
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; color: #333; margin-bottom: 25px; }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .presentation-item {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            background-color: #fcfcfc;
            position: relative; /* For remove button positioning */
        }
        .presentation-item h3 {
            margin-top: 0;
            color: #555;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
            font-size: 1.1em;
        }
        .presentation-item label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #444;
        }
        .presentation-item input[type="text"],
        .presentation-item input[type="number"] {
            width: calc(100% - 20px); /* Adjust for padding */
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
        }
        .add-presentation-btn, .remove-presentation-btn, button[type="submit"] {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s ease;
        }
        .add-presentation-btn { background-color: #007bff; color: white; margin-bottom: 20px; }
        .add-presentation-btn:hover { background-color: #0056b3; }
        .remove-presentation-btn {
            background-color: #dc3545;
            color: white;
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 0.9em;
        }
        .remove-presentation-btn:hover { background-color: #c82333; }
        button[type="submit"] {
            display: block;
            width: 100%;
            background-color: #28a745;
            color: white;
            font-size: 1.1em;
            padding: 12px;
            margin-top: 30px;
        }
        button[type="submit"]:hover { background-color: #218838; }
    </style>
</head>
<body>

    <div class="container">
	<p style="text-align: center; font-size: 0.9em;"><img src="https://github.com/fluidicon.png" height="50px" width="50px"></img><br><a href="https://github.com/Mast3r0mid">My github</a></p>
		 <p style="text-align: right; font-size: 0.9em;">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></strong> | <a href="logout.php">Logout</a></p>
		

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <h2>Edit Presentations</h2>
        <form id="editPresentationsForm" method="POST" action="edit_presentations.php">
            <div id="presentations-list">
                <?php
                if (!empty($presentationsData)) {
                    foreach ($presentationsData as $index => $presentation) {
                        ?>
                        <div class="presentation-item" data-index="<?php echo $index; ?>">
                            <h3>Presentation <?php echo $index + 1; ?></h3>
                            <label for="url_<?php echo $index; ?>">URL:</label>
                            <input type="text" id="url_<?php echo $index; ?>" name="presentations[url][]" value="<?php echo htmlspecialchars($presentation['url'] ?? ''); ?>" required>

                            <label for="duration_<?php echo $index; ?>">Duration for each Slide(ms):</label>
                            <input type="number" id="duration_<?php echo $index; ?>" name="presentations[duration][]" value="<?php echo htmlspecialchars($presentation['duration'] ?? ''); ?>" required></br><label style="font-size: 0.7em;color:red;">Must be 1ms more than your Google Slides 'delayms' value (e.g., 5000 + 1 = 5001) This slight offset ensures the script waits for the slide transition to complete before counting slides.</label>
                            <button type="button" class="remove-presentation-btn" onclick="removePresentation(this)">Remove</button>
                        </div>
                        <?php
                    }
                } else {
                    echo '<p>No presentations found or an error occurred. Add new ones below.</p>';
                }
                ?>
            </div>
            <button type="button" class="add-presentation-btn" onclick="addPresentation()">Add New Presentation</button>
            <button type="submit">Save Changes</button>
        </form>
    </div>

    <script>
        let presentationCounter = <?php echo count($presentationsData); ?>;

        function addPresentation() {
            const presentationsList = document.getElementById('presentations-list');
            const newIndex = presentationCounter++;

            const newItem = document.createElement('div');
            newItem.classList.add('presentation-item');
            newItem.dataset.index = newIndex;
            newItem.innerHTML = `
                <h3>Presentation ${newIndex + 1}</h3>
                <label for="url_${newIndex}">URL:</label>
                <input type="text" id="url_${newIndex}" name="presentations[url][]" value="" required>

                <label for="duration_${newIndex}">Duration (ms):</label>
                <input type="number" id="duration_${newIndex}" name="presentations[duration][]" value="5001" required>
                <button type="button" class="remove-presentation-btn" onclick="removePresentation(this)">Remove</button>
            `;
            presentationsList.appendChild(newItem);
        }

        function removePresentation(button) {
            const itemToRemove = button.closest('.presentation-item');
            if (itemToRemove) {
                itemToRemove.remove();
                // Optional: Re-index displayed numbers after removal (more complex)
                // For simplicity, current method relies on PHP parsing all input fields on submit
            }
        }
    </script>

</body>
</html>