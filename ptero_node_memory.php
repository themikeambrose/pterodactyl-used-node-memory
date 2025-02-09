<?php
// Configuration: Replace with your panel's URL and a valid API key.
$apiUrl = 'https://pterodactyl.yourdomain.com/api/application'; // no trailing slash
$apiKey = 'YOUR_API_KEY_HERE'; // API key should have proper read permissions for node and servers
/**
 * Makes a GET request to the Pterodactyl API and returns the decoded JSON.
 *
 * @param string $endpoint The API endpoint (e.g., '/nodes' or '/servers').
 * @return array The decoded JSON data.
 */
function apiRequest($endpoint) {
    global $apiUrl, $apiKey;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Accept: Application/vnd.pterodactyl.v1+json'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        die('Curl error: ' . curl_error($ch));
    }
    curl_close($ch);
    $data = json_decode($response, true);
    if ($data === null) {
        die('Error decoding JSON response.');
    }
    return $data;
}
/**
 * Retrieve all paginated data from an API endpoint.
 *
 * @param string $endpoint The API endpoint (e.g., '/servers').
 * @param array $params Optional query parameters.
 * @return array An array containing all the items from the 'data' key.
 */
function getAllPages($endpoint, $params = array()) {
    $results = array();
    $page = 1;
    while (true) {
        // Merge additional parameters with the current page number.
        $localParams = array_merge($params, ['page' => $page]);
        $query = http_build_query($localParams);
        $data = apiRequest($endpoint . '?' . $query);
        if (isset($data['data']) && is_array($data['data'])) {
            $results = array_merge($results, $data['data']);
        }
        // Check pagination info to decide if we need to continue.
        if (isset($data['meta']['pagination'])) {
            $pagination = $data['meta']['pagination'];
            $current_page = isset($pagination['current_page']) ? $pagination['current_page'] : $page;
            $total_pages = isset($pagination['total_pages']) ? $pagination['total_pages'] : $page;
            if ($current_page >= $total_pages) {
                break;
            }
        } else {
            // If no pagination data exists, assume single page.
            break;
        }
        $page++;
    }
    return $results;
}
// Retrieve node data (nodes are typically few so pagination might not be necessary).
$nodesData = getAllPages('/nodes');
// Retrieve all server data across all pages.
$serversData = getAllPages('/servers');
// Build an associative array mapping node IDs to their total allocated memory.
$allocatedMemory = array();
foreach ($serversData as $server) {
    $attributes = $server['attributes'];
    // Check for the node attribute.
    if (!isset($attributes['node'])) {
        echo '<pre>';
        echo "Debug: Missing 'node' attribute in server data:\n";
        print_r($server);
        echo '</pre>';
        continue;
    }
    $nodeId = $attributes['node'];
    // Get the server's memory limit allocated (assumed to be in MB).
    $serverMemory = isset($attributes['limits']['memory']) ? $attributes['limits']['memory'] : 0;
    if (!isset($allocatedMemory[$nodeId])) {
        $allocatedMemory[$nodeId] = 0;
    }
    $allocatedMemory[$nodeId] += $serverMemory;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Node Memory Usage</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        table { 
            border-collapse: collapse; 
            width: 80%; 
            margin: 20px auto;
        }
        th, td { 
            border: 1px solid #ccc; 
            padding: 10px; 
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
        }
        .progress {
            background-color: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }
        .progress-bar {
            height: 20px;
            background-color: #4CAF50;
            text-align: center;
            color: white;
            line-height: 20px;
        }
    </style>
</head>
<body>
    <h1 style="text-align: center;">Node Memory Usage</h1>
    <table>
        <tr>
            <th>Node Name</th>
            <th>Allocated Memory (MB)</th>
            <th>Total Memory (MB)</th>
            <th>Usage</th>
        </tr>
        <?php
        // Loop through each node and display its memory usage.
        if (is_array($nodesData)) {
            foreach ($nodesData as $node) {
                $nodeId   = $node['attributes']['id'];
                $nodeName = $node['attributes']['name'];
                $totalMemory = $node['attributes']['memory'];
                $usedMemory = isset($allocatedMemory[$nodeId]) ? $allocatedMemory[$nodeId] : 0;
                $percent = ($totalMemory > 0) ? round(($usedMemory / $totalMemory) * 100, 2) : 0;
                echo "<tr>";
                echo "<td>" . htmlspecialchars($nodeName) . "</td>";
                echo "<td>" . $usedMemory . "</td>";
                echo "<td>" . $totalMemory . "</td>";
                echo "<td>
                        <div class='progress'>
                            <div class='progress-bar' style='width: {$percent}%;'>
                                {$percent}%
                            </div>
                        </div>
                      </td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='4'>No node data found.</td></tr>";
        }
        ?>
    </table>
</body>
</html>
