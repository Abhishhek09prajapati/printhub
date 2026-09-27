<?php
session_start();
require_once('titanconfig.php');

// Sirf Admin hi update chala sakta hai
if (!isset($_SESSION['utype']) || $_SESSION['utype'] !== 'TitanAdmin') {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized Access!']));
}

$zipUrl = $_POST['zip_url'] ?? '';
$newVer = $_POST['version'] ?? '';

if(empty($zipUrl) || empty($newVer)) {
    die(json_encode(['status' => 'error', 'message' => 'Invalid Update Data!']));
}

$zipFile = "install_update.zip";
$extractPath = "../"; // Dashboard ke bahar (root folder) me extract karega

try {
    // 1. Download Zip from Main Server
    $zipData = file_get_contents($zipUrl);
    if($zipData === false) {
        die(json_encode(['status' => 'error', 'message' => 'Failed to download update file from server.']));
    }
    file_put_contents($zipFile, $zipData);

    // 2. Extract Zip
    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        $zip->extractTo($extractPath);
        $zip->close();
        unlink($zipFile); // Extract hone ke baad Zip delete kar dega
        
        // ==============================================================
        // ★ 3. AUTO SQL RUN LOGIC ★
        // ==============================================================
        $sql_file_path = $extractPath . "update.sql";
        
        // Agar zip ke andar update.sql file aayi hai, toh use run karega
        if (file_exists($sql_file_path)) {
            $sql_query = file_get_contents($sql_file_path);
            
            // Execute all SQL queries inside the file
            if ($conn->multi_query($sql_query)) {
                // Multi-query ko flush karna zaroori hota hai
                do {
                    if ($res = $conn->store_result()) {
                        $res->free();
                    }
                } while ($conn->more_results() && $conn->next_result());
            }
            
            // Run hone ke baad file delete kar do (Security Reason)
            unlink($sql_file_path); 
        }
        // ==============================================================
        
        // 4. Update DB Version
        $conn->query("UPDATE settings SET version = '$newVer' WHERE id = 1");

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to open downloaded Zip file.']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>