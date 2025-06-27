<?php
// edit_presentations.php
session_start(); // VERY IMPORTANT: Start the session at the beginning

// --- Authentication Check ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php'); // Redirect to login page if not logged in
    exit();
}

// --- Configuration ---
$playerHtmlFile = 'Player.html'; // Original player.html template for new pages
$pagesXmlFile = 'pages.xml';     // File to store dynamic page (formerly tab) configurations

$message = '';
$message_type = ''; // 'success', 'error', 'info'

// --- Helper function for sending JSON responses for AJAX requests ---
function sendJsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// --- XML Management Functions for Pages ---

/**
 * Reads page configurations from pages.xml.
 * If pages.xml doesn't exist, it creates it with a default 'Main Content' page.
 * @return array Returns an array of page data or an array with an 'error' key.
 */
function readPagesFromXml($filePath) {
    if (!file_exists($filePath)) {
        // If pages.xml doesn't exist, create it with a default 'Main Content' page
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $root = $dom->createElement('pages'); // Root element is now 'pages'
        $dom->appendChild($root);

        // Add default "Main Content" page, which uses Player.html
        $page1 = $dom->createElement('page');
        $page1->setAttribute('name', 'Main Content'); // Display name for the page
        $page1->setAttribute('file', 'Player.html'); // The HTML file associated with this page
        $root->appendChild($page1);

        if ($dom->save($filePath) === false) {
            return ['error' => 'Failed to create initial pages.xml. Check permissions.'];
        }
        // Return the newly created default pages
        return ['data' => [
            ['name' => 'Main Content', 'file' => 'Player.html']
        ]];
    }

    $pagesData = [];
    $xml = simplexml_load_file($filePath);

    if ($xml === false) {
        return ['error' => 'Failed to load pages.xml. Is it valid XML?'];
    }

    foreach ($xml->page as $page) { // Iterate over 'page' elements
        $pagesData[] = [
            'name' => (string)$page['name'],
            'file' => (string)$page['file']
        ];
    }
    return ['data' => $pagesData];
}

/**
 * Writes page configurations to pages.xml.
 * @param string $filePath - Path to pages.xml.
 * @param array $pagesArray - Array of page data (each element like ['name' => '...', 'file' => '...']).
 * @return array Returns an array with 'success' or 'error' key.
 */
function writePagesToXml($filePath, $pagesArray) {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $root = $dom->createElement('pages'); // Root element is now 'pages'
    $dom->appendChild($root);

    foreach ($pagesArray as $pageData) {
        $page = $dom->createElement('page');
        $page->setAttribute('name', $pageData['name']);
        $page->setAttribute('file', $pageData['file']);
        $root->appendChild($page);
    }

    if ($dom->save($filePath) === false) {
        return ['error' => 'Failed to write to pages.xml. Check file permissions.'];
    }
    return ['success' => true];
}

/**
 * Creates a new HTML file for a dynamic page by copying Player.html.
 * @param string $pageFileName - The desired file name for the new page (e.g., 'MyCustomPage.html').
 * @param string $sourceTemplate - Path to the Player.html template.
 * @return array Returns an array with 'success' or 'error' key.
 */
function createPageHtmlFile($pageFileName, $sourceTemplate = 'Player.html') {
    if (!file_exists($sourceTemplate)) {
        return ['error' => "Source template ($sourceTemplate) not found to create new page file."];
    }
    if (file_exists($pageFileName)) {
        return ['error' => "File '$pageFileName' already exists. Choose a different page name."];
    }
    if (!is_writable(dirname($pageFileName))) {
        return ['error' => "Directory for '$pageFileName' is not writable. Check permissions."];
    }

    if (copy($sourceTemplate, $pageFileName)) {
        // Optionally, modify the copied file to ensure it's empty or has default content
        $content = file_get_contents($pageFileName);
        // Replace existing presentations array with an empty one in the new file
        $content = preg_replace('/const\s+presentations\s*=\s*\[[^\]]*?\];/s', 'const presentations = [];', $content);
        file_put_contents($pageFileName, $content);

        return ['success' => true];
    } else {
        return ['error' => "Failed to create new page file '$pageFileName'."];
    }
}

/**
 * Deletes an HTML file associated with a dynamic page.
 * @param string $pageFileName - The file name of the page to delete.
 * @return array Returns an array with 'success' or 'error' key.
 */
function deletePageHtmlFile($pageFileName) {
    // Prevent accidental deletion of critical files
    if ($pageFileName === 'Player.html' || $pageFileName === 'pages.xml' || $pageFileName === 'edit_presentations.php' || $pageFileName === 'login.php' || $pageFileName === 'logout.php') {
        return ['error' => "Cannot delete core system file: $pageFileName."];
    }
    if (!file_exists($pageFileName)) {
        return ['error' => "File '$pageFileName' not found for deletion."];
    }
    if (!is_writable($pageFileName)) {
        return ['error' => "File '$pageFileName' is not writable. Check permissions."];
    }

    if (unlink($pageFileName)) {
        return ['success' => true];
    } else {
        return ['error' => "Failed to delete file '$pageFileName'."];
    }
}


// --- Functions to read/write presentation data *within* a given HTML file ---
// (These remain largely the same, but parameter names updated for clarity)

/**
 * Reads and parses the presentations array from a given HTML file.
 * @param string $htmlFilePath - Path to the HTML file (e.g., 'Player.html' or 'MyCustomPage.html').
 * @return array Returns an array with 'data', 'original_js_block' or 'error' key.
 */
function readPresentationsFromHtml($htmlFilePath) {
    if (!file_exists($htmlFilePath)) {
        return ['error' => 'Target HTML file not found: ' . $htmlFilePath];
    }
    $htmlContent = file_get_contents($htmlFilePath);

    // Regex to find the 'const presentations = [...];' block
    $regex = '/const\s+presentations\s*=\s*(\[[^\]]*?\]);/s';

    if (preg_match($regex, $htmlContent, $matches)) {
        $jsonString = $matches[1]; // Get the content of the array
        $presentations = json_decode($jsonString, true); // Decode as associative array

        if (json_last_error() === JSON_ERROR_NONE) {
            return ['data' => $presentations, 'original_js_block' => $matches[0]];
        } else {
            return ['error' => 'Failed to parse JSON from ' . $htmlFilePath . ': ' . json_last_error_msg()];
        }
    } else {
        // If the block is not found, assume it's an empty array. This allows new pages to work.
        return ['data' => [], 'original_js_block' => 'const presentations = [];'];
    }
}

/**
 * Writes the updated presentations array back to a given HTML file.
 * @param string $htmlFilePath - Path to the HTML file.
 * @param string $oldJsBlock - The original JavaScript block to be replaced.
 * @param array $newPresentationsArray - The array of updated presentation data.
 * @return array Returns an array with 'success' or 'error' key.
 */
function writePresentationsToHtml($htmlFilePath, $oldJsBlock, $newPresentationsArray) {
    if (!file_exists($htmlFilePath)) {
        return ['error' => 'Target HTML file not found: ' . $htmlFilePath . '. Cannot write.'];
    }
    if (!is_writable($htmlFilePath)) {
        return ['error' => 'Target HTML file ' . $htmlFilePath . ' is not writable. Please check file permissions.'];
    }

    $htmlContent = file_get_contents($htmlFilePath);

    // Prepare the new JavaScript array string
    $newJsonString = json_encode($newPresentationsArray, JSON_PRETTY_PRINT);
    $newJsBlock = "const presentations = " . $newJsonString . ";";

    // Replace the old JavaScript block with the new one
    // If oldJsBlock was just 'const presentations = [];', ensure it's found or added
    if (strpos($htmlContent, $oldJsBlock) !== false) {
        $updatedHtmlContent = str_replace($oldJsBlock, $newJsBlock, $htmlContent);
    } else {
        // Fallback: if the oldJsBlock wasn't found (e.g., in a newly copied file with modified content),
        // try to find a generic presentations declaration or append it.
        if (preg_match('/const\s+presentations\s*=\s*\[[^\]]*?\];/s', $htmlContent, $matches)) {
            $updatedHtmlContent = preg_replace('/const\s+presentations\s*=\s*\[[^\]]*?\];/s', $newJsBlock, $htmlContent);
        } else {
            // Append it before </body> if no existing block. This is a robust fallback for new files.
            $updatedHtmlContent = str_replace('</body>', "<script>\n" . $newJsBlock . "\n</script>\n</body>", $htmlContent);
        }
    }


    if (file_put_contents($htmlFilePath, $updatedHtmlContent) !== false) {
        return ['success' => true];
    } else {
        return ['error' => 'Failed to write updated content to ' . $htmlFilePath . '.'];
    }
}


// --- Handle AJAX Requests for Page Management ---
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'add_page') {
        $pageName = trim($_POST['page_name'] ?? '');
        if (empty($pageName)) {
            sendJsonResponse(['status' => 'error', 'message' => 'Page name cannot be empty.']);
        }

        // Sanitize page name to create a safe file name
        $pageFileName = preg_replace('/[^a-zA-Z0-9_-]/', '', $pageName); // Remove non-alphanumeric chars
        $pageFileName = strtolower($pageFileName) . '.html'; // Convert to lowercase and add .html extension

        $readResult = readPagesFromXml($pagesXmlFile);
        if (isset($readResult['error'])) {
            sendJsonResponse(['status' => 'error', 'message' => $readResult['error']]);
        }
        $pagesData = $readResult['data'];

        // Check for duplicate page names or file names
        foreach ($pagesData as $page) {
            if (strtolower($page['name']) === strtolower($pageName)) {
                sendJsonResponse(['status' => 'error', 'message' => 'A page with this name already exists.']);
            }
            if (strtolower($page['file']) === strtolower($pageFileName)) {
                sendJsonResponse(['status' => 'error', 'message' => 'A file for this page name already exists.']);
            }
        }

        // 1. Create the new HTML file by copying Player.html
        $createFileResult = createPageHtmlFile($pageFileName, $playerHtmlFile);
        if (isset($createFileResult['error'])) {
            sendJsonResponse(['status' => 'error', 'message' => $createFileResult['error']]);
        }

        // 2. Add the new page to the XML structure
        $pagesData[] = ['name' => $pageName, 'file' => $pageFileName];
        $writeResult = writePagesToXml($pagesXmlFile, $pagesData);

        if (isset($writeResult['success'])) {
            sendJsonResponse(['status' => 'success', 'message' => 'Page "' . htmlspecialchars($pageName) . '" added successfully!', 'pageName' => $pageName, 'pageFile' => $pageFileName]);
        } else {
            // If writing to XML fails, try to clean up the created HTML file
            if (file_exists($pageFileName)) {
                unlink($pageFileName); // Delete the partially created file
            }
            sendJsonResponse(['status' => 'error', 'message' => $writeResult['error']]);
        }

    } elseif ($_POST['action'] === 'remove_page') {
        $pageFileToRemove = trim($_POST['page_file'] ?? ''); // This is the file name associated with the page

        // Basic validation for critical files (Player.html is now a core page)
        if (empty($pageFileToRemove) || $pageFileToRemove === 'pages.xml' || $pageFileToRemove === 'edit_presentations.php' || $pageFileToRemove === 'login.php' || $pageFileToRemove === 'logout.php') {
            sendJsonResponse(['status' => 'error', 'message' => 'Cannot remove this critical system file.']);
        }

        $readResult = readPagesFromXml($pagesXmlFile);
        if (isset($readResult['error'])) {
            sendJsonResponse(['status' => 'error', 'message' => $readResult['error']]);
        }
        $pagesData = $readResult['data'];

        $found = false;
        $updatedPages = [];
        foreach ($pagesData as $page) {
            if ($page['file'] === $pageFileToRemove) {
                $found = true;
            } else {
                $updatedPages[] = $page; // Keep pages that are not being removed
            }
        }

        if (!$found) {
            sendJsonResponse(['status' => 'error', 'message' => 'Page not found in pages.xml.']);
        }
        // Ensure at least one page remains if trying to delete the last one
        if (count($updatedPages) < 1) {
             sendJsonResponse(['status' => 'error', 'message' => 'Cannot remove the last page. At least one page must exist.']);
        }


        // 1. Delete the associated HTML file
        $deleteFileResult = deletePageHtmlFile($pageFileToRemove);
        if (isset($deleteFileResult['error'])) {
            sendJsonResponse(['status' => 'error', 'message' => $deleteFileResult['error']]);
        }

        // 2. Remove the page from the XML structure
        $writeResult = writePagesToXml($pagesXmlFile, $updatedPages);

        if (isset($writeResult['success'])) {
            sendJsonResponse(['status' => 'success', 'message' => 'Page and its file removed successfully!']);
        } else {
            sendJsonResponse(['status' => 'error', 'message' => $writeResult['error']]);
        }

    } elseif ($_POST['action'] === 'load_page_content') {
        $targetFile = trim($_POST['target_file'] ?? '');
        $readResult = readPresentationsFromHtml($targetFile);
        if (isset($readResult['error'])) {
            sendJsonResponse(['status' => 'error', 'message' => $readResult['error']]);
        } else {
            sendJsonResponse(['status' => 'success', 'data' => $readResult['data']]);
        }

    } elseif ($_POST['action'] === 'save_page_content') {
        $targetFile = trim($_POST['target_file'] ?? '');
        
        // Ensure the target file is an HTML file and exists
        if (!preg_match('/\.html$/i', $targetFile) || !file_exists($targetFile)) {
             sendJsonResponse(['status' => 'error', 'message' => 'Invalid target file specified for saving: ' . htmlspecialchars($targetFile)]);
        }

        // This action now specifically saves presentation data to the target HTML file
        if (isset($_POST['presentations']['url']) && isset($_POST['presentations']['duration']) &&
            is_array($_POST['presentations']['url']) && is_array($_POST['presentations']['duration']) &&
            count($_POST['presentations']['url']) === count($_POST['presentations']['duration'])) {

            $readResult = readPresentationsFromHtml($targetFile);
            if (isset($readResult['error'])) {
                sendJsonResponse(['status' => 'error', 'message' => $readResult['error']]);
            }
            $oldJsBlock = $readResult['original_js_block'];
            $updatedPresentations = [];

            foreach ($_POST['presentations']['url'] as $index => $url) {
                $duration = (int)$_POST['presentations']['duration'][$index];
                $updatedPresentations[] = [
                    'url' => htmlspecialchars($url), // Sanitize input
                    'duration' => $duration,
                    'originalDuration' => $duration // Assuming originalDuration is same as duration
                ];
            }

            $writeResult = writePresentationsToHtml($targetFile, $oldJsBlock, $updatedPresentations);
            if (isset($writeResult['success'])) {
                sendJsonResponse(['status' => 'success', 'message' => 'Presentations saved to ' . htmlspecialchars($targetFile) . '.']);

            } else {
                sendJsonResponse(['status' => 'error', 'message' => $writeResult['error']]);
            }
        } else {
            sendJsonResponse(['status' => 'error', 'message' => 'Invalid presentation data received for saving to ' . htmlspecialchars($targetFile) . '.']);
        }
    }
    // No match for action, potentially an invalid AJAX request
    sendJsonResponse(['status' => 'error', 'message' => 'Invalid AJAX action.']);
}


// --- Handle Regular Form Submission (legacy for presentations form, if still used) ---
// This block is now largely superseded by the 'save_page_content' AJAX action.
// It is kept for backward compatibility if the original form submits directly without AJAX.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['presentations']) && !isset($_POST['action'])) {
    // This part would specifically target the "Main Content" page (Player.html)
    $readResult = readPresentationsFromHtml($playerHtmlFile);

    if (isset($readResult['error'])) {
        $message = $readResult['error'];
        $message_type = 'error';
    } else {
        $oldJsBlock = $readResult['original_js_block'];
        $updatedPresentations = [];

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

            $writeResult = writePresentationsToHtml($playerHtmlFile, $oldJsBlock, $updatedPresentations);

            if (isset($writeResult['success'])) {
                // Redirect to self to clear POST data and show message
                header('Location: edit_presentations.php?status=success');
                exit();
            } else {
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

// --- Display initial status message after redirect (for non-AJAX saves) ---
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $message = 'Operation successful!'; // More generic message
        $message_type = 'success';
    } elseif ($_GET['status'] === 'error') {
        $message = $_GET['msg'] ?? 'An error occurred during operation.';
        $message_type = 'error';
    }
}


// --- Read All Pages for Initial Display in the "Content" tab ---
$allPages = [];
$readPagesResult = readPagesFromXml($pagesXmlFile); // Note: Renamed function
if (isset($readPagesResult['error'])) {
    $message = $readPagesResult['error'];
    $message_type = 'error';
} else {
    $allPages = $readPagesResult['data'];
}

// Placeholder for content of the File Edit tab (if you re-introduce it)
function getFileEditContentHtml() {
    return '
        <h3 class="text-xl font-semibold mb-3 text-gray-800">File Management Options</h3>
        <p class="text-gray-700 mb-4">This section can be used for general file uploads and management if needed.</p>
        <form action="upload_file.php" method="post" enctype="multipart/form-data">
            <label for="fileToUpload" class="text-gray-800 block mb-2">Select file to upload:</label>
            <input type="file" name="fileToUpload" id="fileToUpload" class="border p-2 rounded-md w-full mb-4">
            <input type="submit" value="Upload File" name="submit" class="bg-purple-600 text-white p-2 rounded-md hover:bg-purple-700 cursor-pointer">
        </form>
        <div class="mt-8">
            <h3 class="text-xl font-semibold mb-3 text-gray-800">Existing Files</h3>
            <ul class="list-disc pl-5">
                <li class="mb-2">document.pdf <button class="text-red-500 hover:underline text-sm ml-2">Delete</button></li>
                <li class="mb-2">image.png <button class="text-red-500 hover:underline text-sm ml-2">Delete</button></li>
                <li>report.docx <button class="text-red-500 hover:underline text-sm ml-2">Delete</button></li>
            </ul>
        </div>
    ';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management</title>
    <link href="https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 900px;
            box-sizing: border-box;
            border: 1px solid #e0e0e0;
        }

        /* Message Box Styling */
        .message {
            padding: 12px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 0.95em;
            text-align: center;
            border: 1px solid;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .message.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }

        /* Tab Styling (for main Content tab) */
        .tab-nav {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 20px;
            flex-wrap: wrap; /* Allow tabs to wrap on smaller screens */
            gap: 5px; /* Spacing between tabs */
        }

        .tab-button {
            background-color: #f0f0f0;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 14px 20px;
            transition: all 0.3s ease;
            font-size: 1em;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            color: #555;
            position: relative; /* For the close button */
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap; /* Prevent tab names from breaking */
            max-width: 200px; /* Limit tab width */
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tab-button:hover {
            background-color: #e0e0e0;
        }

        .tab-button.active {
            background-color: #fff;
            border: 2px solid #e0e0e0;
            border-bottom: 2px solid #fff; /* To make it look connected to the content */
            color: #333;
            font-weight: 600;
        }

        /* Content tab specific styles */
        #content-tab-container { /* This is the main div for the 'Content' tab */
            display: block; /* Always visible for this fixed tab */
            padding: 20px 0;
        }

        /* Page List Styling within the Content Tab */
        .page-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            margin-bottom: 10px;
            transition: background-color 0.2s ease;
        }
        .page-item:hover {
            background-color: #f0f0f0;
        }
        .page-item-name {
            font-size: 1.1em;
            font-weight: 500;
            color: #333;
            flex-grow: 1;
            margin-right: 15px;
        }
        .page-item-actions button {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            margin-left: 8px;
            transition: background-color 0.2s ease;
        }
        .page-item-actions .edit-page-btn {
            background-color: #007bff;
            color: white;
        }
        .page-item-actions .edit-page-btn:hover {
            background-color: #0056b3;
        }
        .page-item-actions .remove-page-btn {
            background-color: #dc3545;
            color: white;
        }
        .page-item-actions .remove-page-btn:hover {
            background-color: #c82333;
        }

        /* Page Editor Area (for loading content dynamically) */
        #page-editor-area {
            background-color: #ffffff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-top: 20px;
            display: none; /* Hidden by default until a page is selected */
            border: 1px solid #e0e0e0;
        }

        /* Existing Form Styling (adjusted for new context) */
        form {
            background-color: #f9f9f9; /* Retained for individual page forms */
            padding: 25px;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }

        form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        form input[type="text"],
        form input[type="number"],
        form input[type="file"],
        form textarea {
            width: calc(100% - 22px); /* Account for padding/border */
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1em;
            box-sizing: border-box;
        }

        form button[type="submit"],
        .add-presentation-btn,
        .remove-presentation-btn,
        .add-page-btn { /* Added add-page-btn */
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }
        .add-page-btn {
            background-color: #28a745;
            color: white;
            margin-bottom: 20px;
        }
        .add-page-btn:hover {
            background-color: #218838;
        }

        form button[type="submit"] {
            background-color: #28a745;
            color: white;
            margin-right: 10px;
        }

        form button[type="submit"]:hover {
            background-color: #218838;
        }

        .add-presentation-btn {
            background-color: #6c757d;
            color: white;
            margin-right: 10px;
        }

        .add-presentation-btn:hover {
            background-color: #5a6268;
        }

        .remove-presentation-btn {
            background-color: #ffc107;
            color: #333;
            margin-top: 10px;
        }

        .remove-presentation-btn:hover {
            background-color: #e0a800;
        }

        .presentation-item {
            background-color: #f1f1f1;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px dashed #ccc;
        }

        .presentation-item h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }

        /* Custom Message Box (replaces alert/confirm) */
        .message-box-custom {
            background-color: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 1000;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            min-width: 320px;
            max-width: 90%;
            text-align: center;
            border: 1px solid #ddd;
        }
        .message-box-custom p {
            margin-bottom: 20px;
            font-size: 1.1em;
            color: #333;
        }
        .message-box-custom button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s ease;
        }
        .message-box-custom button.bg-blue-500 { background-color: #007bff; color: white; }
        .message-box-custom button.bg-blue-500:hover { background-color: #0056b3; }
        .message-box-custom button.bg-gray-400 { background-color: #6c757d; color: white; }
        .message-box-custom button.bg-gray-400:hover { background-color: #5a6268; }

    </style>
</head>
<body>

    <div class="container">
        <p class="text-center text-sm mb-4">
            
            <a href="https://github.com/Mast3r0mid" class="text-blue-600 hover:underline"><img src="https://github.com/fluidicon.png" height="50px" width="50px" class="inline-block rounded-full shadow-md"><br>My github</a>
        </p>
        <p class="text-right text-sm text-gray-700 mb-6">
            Logged in as: <strong class="text-gray-900"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></strong> |
            <a href="logout.php" class="text-blue-600 hover:underline">Logout</a>
        </p>

        <!-- PHP Message Placeholder -->
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Main Tab Navigation -->
        <div class="tab-nav" id="mainTabNav">
            <button class="tab-button active" data-tab-id="content-tab-container" onclick="openMainTab(event, 'content-tab-container')">Content</button>
            <!-- If you want to add other static tabs later, they would go here -->
            <!-- <button class="tab-button" data-tab-id="file-edit-tab-container" onclick="openMainTab(event, 'file-edit-tab-container')">File Edit</button> -->
        </div>


        <!-- Main Tab Content Container -->
        <div id="mainTabContentContainer">
            <!-- Content Tab: Lists pages and provides editor -->
            <div id="content-tab-container" class="main-tab-content active">
                <h2>Manage Content Pages</h2>

                <button class="add-page-btn" onclick="addNewPage()">Add New Page</button>

                <div id="pages-list" class="mt-6">
                    <h3 class="text-lg font-semibold mb-3">Your Pages:</h3>
                    <?php if (!empty($allPages)): ?>
                        <?php foreach ($allPages as $page): ?>
                            <div class="page-item" data-page-file="<?php echo htmlspecialchars($page['file']); ?>">
                                <span class="page-item-name"><a href="<?php echo htmlspecialchars($page['file']); ?> " title="View Player" target="_blank"><?php echo htmlspecialchars($page['name']); ?></a></span>
                                <div class="page-item-actions">
                                    <button class="edit-page-btn" onclick="editPageContent('<?php echo htmlspecialchars($page['file']); ?>', '<?php echo htmlspecialchars($page['name']); ?>')">Edit</button>
                                    <button class="remove-page-btn" onclick="removePage('<?php echo htmlspecialchars($page['file']); ?>', '<?php echo htmlspecialchars($page['name']); ?>')">Remove</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-600">No pages found. Click "Add New Page" to create one.</p>
                    <?php endif; ?>
                </div>

                <!-- Area where individual page's content (presentations form) will be loaded -->
                <div id="page-editor-area" style="display: none;">
                    <h2 id="current-page-title"></h2>
                    <form class="presentation-form" method="POST" action="edit_presentations.php">
                        <input type="hidden" name="action" value="save_page_content">
                        <input type="hidden" name="target_file" id="current-page-file" value="">
                        <div id="presentations-list">
                            <!-- Presentations will be loaded here via AJAX -->
                        </div>
                        <button type="button" class="add-presentation-btn" onclick="addPresentation()">Add New Presentation</button>
                        <button type="submit">Save Page Changes</button>
                        <button type="button" class="add-presentation-btn bg-gray-500 hover:bg-gray-600 ml-2" onclick="cancelEditPage()">Cancel</button>
                    </form>
                </div>

            </div>

            <!-- Optional: File Edit Tab (currently removed as per request, but can be re-added) -->
            <!--
            <div id="file-edit-tab-container" class="main-tab-content">
                <h2>File Management</h2>
                <?php // echo getFileEditContentHtml(); ?>
            </div>
            -->
        </div>
    </div>

    <script>
        // Global variable to keep track of the currently loaded page's file
        let currentPageFile = null;
        let presentationCounter = 0; // Tracks presentations for the *currently loaded* page

        /**
         * Opens the specified main tab and sets its button as active.
         * @param {Event} evt - The event object from the clicked tab button.
         * @param {string} tabId - The ID of the main tab content div to show.
         */
        function openMainTab(evt, tabId) {
            // Hide all main tab contents
            document.querySelectorAll('.main-tab-content').forEach(content => {
                content.style.display = 'none';
            });

            // Deactivate all main tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });

            // Show the target main tab content
            const targetContent = document.getElementById(tabId);
            if (targetContent) {
                targetContent.style.display = 'block';
            }

            // Activate the clicked button
            if (evt && evt.currentTarget) {
                evt.currentTarget.classList.add('active');
            }
            // Hide the page editor area when switching main tabs
            document.getElementById('page-editor-area').style.display = 'none';
        }

        /**
         * Adds a new page (HTML file and XML entry) via AJAX.
         */
        async function addNewPage() {
            const pageName = await prompt('Enter a name for the new page:');
            if (pageName === null || !pageName.trim()) {
                displayMessage('Page creation cancelled or name was empty.', 'info');
                return;
            }
            if (pageName.length < 3) {
                 displayMessage('Page name must be at least 3 characters long.', 'error');
                 return;
            }

            displayMessage('Adding new page...', 'info');

            try {
                const response = await fetch('edit_presentations.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'add_page', // New AJAX action
                        page_name: pageName
                    })
                });
                const result = await response.json();

                if (result.status === 'success') {
                    displayMessage(result.message, 'success');
                    // Add the new page to the client-side list
                    addPageToList(result.pageName, result.pageFile);
                    // Hide the editor area after adding a new page
                    cancelEditPage(); 
					
                } else {
                    displayMessage(result.message, 'error');
                }
            } catch (error) {
                console.error('Error adding new page:', error);
                displayMessage('An error occurred while adding the page.', 'error');
            }
        }

        // ... (rest of your script) ...

        // Initial setup on page load
        document.addEventListener("DOMContentLoaded", function() {
            // Ensure the "Content" tab is active by default
            openMainTab(null, 'content-tab-container');

            // Intercept form submission for the page-editor's presentation form to use AJAX
            const presentationForm = document.querySelector('#page-editor-area .presentation-form');
            if (presentationForm) {
                presentationForm.addEventListener('submit', async function(event) {
                    event.preventDefault(); // Prevent default form submission

                    const formData = new FormData(this);
                    // action and target_file are already set in hidden inputs

                    displayMessage('Saving page changes...', 'info');

                    try {
                        // Corrected: Explicitly use the PHP script path for the fetch URL
                        const response = await fetch('edit_presentations.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.status === 'success') {
                            displayMessage(result.message, 'success');
                            // Hide the editor area after successfully saving changes
                            cancelEditPage();
                        } else {
                            displayMessage(result.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error saving presentation data:', error);
                        displayMessage('An error occurred while saving presentations.', 'error');
                    }
                });
            }
        });

        /**
         * Adds a new page item to the 'Your Pages' list in the Content tab.
         * @param {string} pageName - The display name of the page.
         * @param {string} pageFile - The associated HTML file name.
         */
        function addPageToList(pageName, pageFile) {
            const pagesList = document.getElementById('pages-list');
            let pagesFound = pagesList.querySelector('p.text-gray-600');
            if (pagesFound && pagesFound.textContent === 'No pages found. Click "Add New Page" to create one.') {
                pagesFound.remove(); // Remove the "No pages found" message
                pagesList.innerHTML = '<h3 class="text-lg font-semibold mb-3">Your Pages:</h3>' + pagesList.innerHTML; // Re-add title
            }

            const newPageItem = document.createElement('div');
            newPageItem.className = 'page-item';
            newPageItem.setAttribute('data-page-file', pageFile);
            newPageItem.innerHTML = `
                
				<span class="page-item-name"><a href="${pageFile}" title="View Player" target="_blank">${pageName}</a></span>
                <div class="page-item-actions">
                    <button class="edit-page-btn" onclick="editPageContent('${pageFile}', '${pageName}')">Edit</button>
                    <button class="remove-page-btn" onclick="removePage('${pageFile}', '${pageName}')">Remove</button>
                </div>
            `;
            pagesList.appendChild(newPageItem);
        }

        /**
         * Removes a specific page (HTML file and XML entry) via AJAX.
         * @param {string} pageFile - The file name of the page to remove.
         * @param {string} pageName - The display name of the page (for confirmation).
         */
        async function removePage(pageFile, pageName) {
            // Prevent removing critical files (Player.html is now considered a page)
            if (pageFile === 'Player.html') {
                displayMessage('Cannot remove the main content page (' + pageName + ').', 'error');
                return;
            }

            if (!(await confirm(`Are you sure you want to remove the page "${pageName}" and its associated file? This cannot be undone.`))) {
                return;
            }

            displayMessage('Removing page...', 'info');

            try {
                const response = await fetch('edit_presentations.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'remove_page', // New AJAX action
                        page_file: pageFile
                    })
                });
                const result = await response.json();

                if (result.status === 'success') {
                    displayMessage(result.message, 'success');
                    // Remove the page from the client-side list
                    const pageItemToRemove = document.querySelector(`.page-item[data-page-file="${pageFile}"]`);
                    if (pageItemToRemove) {
                        pageItemToRemove.remove();
                    }
                    // Hide editor if the removed page was being edited
                    if (currentPageFile === pageFile) {
                        document.getElementById('page-editor-area').style.display = 'none';
                        currentPageFile = null;
                    }
                    // If no pages left, show the "No pages found" message
                    if (document.querySelectorAll('.page-item').length === 0) {
                        const pagesList = document.getElementById('pages-list');
                        pagesList.innerHTML = '<h3 class="text-lg font-semibold mb-3">Your Pages:</h3><p class="text-gray-600">No pages found. Click "Add New Page" to create one.</p>';
                    }
                } else {
                    displayMessage(result.message, 'error');
                }
            } catch (error) {
                console.error('Error removing page:', error);
                displayMessage('An error occurred while removing the page.', 'error');
            }
        }

        /**
         * Loads content for a specific page into the editor area via AJAX.
         * @param {string} pageFile - The file name of the page to edit.
         * @param {string} pageName - The display name of the page.
         */
        async function editPageContent(pageFile, pageName) {
            displayMessage(`Loading content for "${pageName}"...`, 'info');
            
            currentPageFile = pageFile;
            document.getElementById('current-page-title').textContent = `Edit Presentations for: ${pageName}`;
            document.getElementById('current-page-file').value = pageFile;
            document.getElementById('page-editor-area').style.display = 'block';

            // Scroll to the editor area
            document.getElementById('page-editor-area').scrollIntoView({ behavior: 'smooth', block: 'start' });

            try {
                const response = await fetch('edit_presentations.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'load_page_content', // New AJAX action
                        target_file: pageFile
                    })
                });
                const result = await response.json();

                if (result.status === 'success') {
                    displayMessage(`Content for "${pageName}" loaded.`, 'success');
                    populatePresentationsForm(result.data);
                } else {
                    displayMessage(result.message, 'error');
                    populatePresentationsForm([]); // Clear form on error
                }
            } catch (error) {
                console.error('Error loading page content:', error);
                displayMessage('An error occurred while loading page content.', 'error');
                populatePresentationsForm([]); // Clear form on error
            }
        }

        /**
         * Populates the presentations form with data fetched from a page.
         * @param {Array} presentations - An array of presentation objects.
         */
        function populatePresentationsForm(presentations) {
            const presentationsList = document.getElementById('presentations-list');
            presentationsList.innerHTML = ''; // Clear existing items

            presentationCounter = 0; // Reset counter for the new page

            if (presentations && presentations.length > 0) {
                presentations.forEach((presentation, index) => {
                    const newPresentationItem = document.createElement('div');
                    newPresentationItem.className = 'presentation-item';
                    newPresentationItem.dataset.index = index;
                    newPresentationItem.innerHTML = `
                        <h3>Presentation ${index + 1}</h3>
                        <label for="url_${index}">URL:</label>
                        <input type="text" id="url_${index}" name="presentations[url][]" value="${escapeHtml(presentation.url || '')}" required>

                        <label for="duration_${index}">Duration for each Slide(ms):</label>
                        <input type="number" id="duration_${index}" name="presentations[duration][]" value="${escapeHtml(presentation.duration || '')}" required><br><label style="font-size: 0.7em;color:red;">Must be 1ms more than your Google Slides 'delayms' value (e.g., 5000 + 1 = 5001) This slight offset ensures the script waits for the slide transition to complete before counting slides.</label>
                        <button type="button" class="remove-presentation-btn" onclick="removePresentation(this)">Remove</button>
                    `;
                    presentationsList.appendChild(newPresentationItem);
                });
                presentationCounter = presentations.length; // Update counter based on loaded items
            } else {
                presentationsList.innerHTML = '<p class="text-gray-600">No presentations found for this page. Add new ones below.</p>';
            }
        }

        /**
         * Clears the page editor area and hides it.
         */
        function cancelEditPage() {
            document.getElementById('page-editor-area').style.display = 'none';
            document.getElementById('presentations-list').innerHTML = ''; // Clear presentations
            currentPageFile = null;
        }


        // --- Functions for managing presentations within a loaded page ---

        /**
         * Adds a new presentation input block to the current page's form.
         */
        function addPresentation() {
            const presentationsList = document.getElementById('presentations-list');
            const newIndex = presentationCounter++; // Use the current global counter

            const newPresentationItem = document.createElement('div');
            newPresentationItem.className = 'presentation-item';
            newPresentationItem.dataset.index = newIndex;

            // Remove the "No presentations found" message if it exists
            const noPresentationsMsg = presentationsList.querySelector('p.text-gray-600');
            if (noPresentationsMsg) {
                noPresentationsMsg.remove();
            }

            newPresentationItem.innerHTML = `
                <h3>Presentation ${newIndex + 1}</h3>
                <label for="url_${newIndex}">URL:</label>
                <input type="text" id="url_${newIndex}" name="presentations[url][]" required>

                <label for="duration_${newIndex}">Duration for each Slide(ms):</label>
                <input type="number" id="duration_${newIndex}" name="presentations[duration][]" value="5001" required><br><label style="font-size: 0.7em;color:red;">Must be 1ms more than your Google Slides 'delayms' value (e.g., 5000 + 1 = 5001) This slight offset ensures the script waits for the slide transition to complete before counting slides.</label>
                <button type="button" class="remove-presentation-btn" onclick="removePresentation(this)">Remove</button>
            `;
            presentationsList.appendChild(newPresentationItem);
        }

        /**
         * Removes a presentation input block from the current page's form.
         * Re-indexes remaining presentation blocks to maintain visual order.
         * @param {HTMLElement} button - The remove button that was clicked.
         */
        function removePresentation(button) {
            const presentationItem = button.closest('.presentation-item');
            if (presentationItem) {
                presentationItem.remove();

                // Re-index remaining presentations to ensure sequential numbering
                const presentationsList = document.getElementById('presentations-list');
                const remainingItems = Array.from(presentationsList.children);

                if (remainingItems.length === 0) {
                    presentationsList.innerHTML = '<p class="text-gray-600">No presentations found for this page. Add new ones below.</p>';
                    presentationCounter = 0; // Reset counter if list is empty
                } else {
                    remainingItems.forEach((item, index) => {
                        item.dataset.index = index;
                        item.querySelector('h3').textContent = `Presentation ${index + 1}`;
                        // Update IDs to maintain uniqueness if new items are added later
                        item.querySelectorAll('label[for^="url_"]').forEach(el => el.setAttribute('for', `url_${index}`));
                        item.querySelectorAll('input[id^="url_"]').forEach(el => el.id = `url_${index}`);
                        item.querySelectorAll('label[for^="duration_"]').forEach(el => el.setAttribute('for', `duration_${index}`));
                        item.querySelectorAll('input[id^="duration_"]').forEach(el => el.id = `duration_${index}`);
                    });
                     // Re-adjust presentationCounter to the count of remaining items for correct next index
                    presentationCounter = remainingItems.length;
                }
            }
        }

        // --- Utility for HTML escaping ---
        function escapeHtml(text) {
            // Ensure the input is treated as a string
            const strText = String(text || ''); // Convert to string, treat null/undefined as empty string
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return strText.replace(/[&<>"']/g, function(m) { return map[m]; });
        }


        // --- Custom Message Box (replaces alert/confirm) ---

        /**
         * Displays a custom message box instead of `alert()` or `confirm()`.
         * @param {string} message - The message to display.
         * @param {string} type - The type of message (e.g., 'success', 'error', 'info').
         * @param {function} [onConfirmCallback=null] - Callback function for 'OK' or 'Confirm' button.
         */
        function displayMessage(message, type, onConfirmCallback = null) {
            let messageDiv = document.querySelector('.message-box-custom');

            if (!messageDiv) {
                messageDiv = document.createElement('div');
                messageDiv.className = 'message-box-custom'; // Basic class
                messageDiv.innerHTML = `
                    <p class="mb-4 text-lg font-semibold" id="messageBoxText"></p>
                    <div class="message-box-buttons">
                        <button class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 mr-2" id="messageBoxOk">OK</button>
                        <button class="bg-gray-400 text-white px-4 py-2 rounded-md hover:bg-gray-500" id="messageBoxCancel" style="display:none;">Cancel</button>
                    </div>
                `;
                document.body.appendChild(messageDiv);

                document.getElementById('messageBoxOk').onclick = function() {
                    messageDiv.remove();
                    if (onConfirmCallback) {
                        onConfirmCallback(true);
                    }
                };
                document.getElementById('messageBoxCancel').onclick = function() {
                    messageDiv.remove();
                    if (onConfirmCallback) {
                        onConfirmCallback(false);
                    }
                };
            }

            const messageText = messageDiv.querySelector('#messageBoxText');
            messageText.textContent = message;

            const okButton = messageDiv.querySelector('#messageBoxOk');
            const cancelButton = messageDiv.querySelector('#messageBoxCancel');

            if (onConfirmCallback) {
                okButton.textContent = 'Confirm';
                cancelButton.style.display = 'inline-block';
            } else {
                okButton.textContent = 'OK';
                cancelButton.style.display = 'none';
            }

            // Optional: style message box text based on type
            messageText.style.color = ''; // Reset color
            if (type === 'success') {
                messageText.style.color = '#155724';
            } else if (type === 'error') {
                messageText.style.color = '#721c24';
            } else if (type === 'info') {
                messageText.style.color = '#0c5460';
            }
        }

        // Custom confirm function using displayMessage
        function confirm(message) {
            return new Promise((resolve) => {
                displayMessage(message, 'info', (result) => {
                    resolve(result);
                });
            });
        }


        // Initial setup on page load
        document.addEventListener("DOMContentLoaded", function() {
            // Ensure the "Content" tab is active by default
            openMainTab(null, 'content-tab-container');

            // Intercept form submission for the page-editor's presentation form to use AJAX
            const presentationForm = document.querySelector('#page-editor-area .presentation-form');
            if (presentationForm) {
                presentationForm.addEventListener('submit', async function(event) {
                    event.preventDefault(); // Prevent default form submission

                    const formData = new FormData(this);
                    // action and target_file are already set in hidden inputs

                    displayMessage('Saving page changes...', 'info');

                    try {
                        // Corrected: Explicitly use the PHP script path for the fetch URL
                        const response = await fetch('edit_presentations.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.status === 'success') {
                            displayMessage(result.message, 'success');
                        } else {
                            displayMessage(result.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error saving presentation data:', error);
                        displayMessage('An error occurred while saving presentations.', 'error');
                    }
                });
            }
        });
    </script>
</body>
</html>
