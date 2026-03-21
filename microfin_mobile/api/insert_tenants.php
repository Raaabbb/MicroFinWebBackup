<?php
$conn = new mysqli("localhost", "root", "", "microfin_db");

$sql = "
INSERT IGNORE INTO tenants (tenant_id, tenant_name, tenant_slug) VALUES 
('fundline', 'Fundline', 'fundline'),
('plaridel', 'PlaridelMFB', 'plaridel'),
('sacredheart', 'Sacred Heart Coop', 'sacredheart');

INSERT IGNORE INTO user_roles (tenant_id, role_name, role_description) VALUES 
('fundline', 'Client', 'Client role'),
('plaridel', 'Client', 'Client role'),
('sacredheart', 'Client', 'Client role');
";

if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "All tenants inserted successfully!\n";
} else {
    echo "Error inserting tenants: " . $conn->error;
}
?>
