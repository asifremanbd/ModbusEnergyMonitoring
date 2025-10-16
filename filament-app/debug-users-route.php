<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "=== Debug Users Route ===\n\n";

try {
    // Create a fake request to the users route
    $request = Illuminate\Http\Request::create('/admin/users', 'GET');
    
    // Set up the request context
    $request->headers->set('Host', '127.0.0.1:8000');
    
    echo "Testing route: /admin/users\n";
    
    // Try to resolve the route
    $route = app('router')->getRoutes()->match($request);
    
    if ($route) {
        echo "✓ Route found: " . $route->getName() . "\n";
        echo "✓ Action: " . $route->getActionName() . "\n";
        echo "✓ Methods: " . implode(', ', $route->methods()) . "\n";
        
        // Check if the controller/page exists
        $action = $route->getAction();
        if (isset($action['controller'])) {
            echo "✓ Controller: " . $action['controller'] . "\n";
        }
        
    } else {
        echo "✗ Route not found\n";
    }
    
    // Test if we can instantiate the ListUsers page
    echo "\nTesting ListUsers page...\n";
    $listUsersPage = new App\Filament\Resources\UserResource\Pages\ListUsers();
    echo "✓ ListUsers page instantiated successfully\n";
    
    // Test the resource
    echo "\nTesting UserResource...\n";
    $resource = App\Filament\Resources\UserResource::class;
    echo "✓ Resource class: " . $resource . "\n";
    echo "✓ Model: " . $resource::getModel() . "\n";
    echo "✓ Navigation Label: " . $resource::getNavigationLabel() . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}