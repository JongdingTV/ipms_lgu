<?php
/**
 * Citizen Portal Scope & Capabilities
 * 
 * This file defines what citizens can and cannot do in the IPMS system
 */

$CITIZEN_SCOPE = [
    'name' => 'Citizen / Public User',
    'description' => 'Public engagement and project transparency portal',
    
    'permissions' => [
        // Project Access
        'projects:view_all' => 'Can view all public projects',
        'projects:search' => 'Can search and filter projects',
        'projects:view_details' => 'Can view project details and status',
        'projects:view_timeline' => 'Can view project timelines and milestones',
        'projects:view_budget' => 'Can view project budgets (read-only)',
        'projects:view_expenses' => 'Can view project expenses (read-only)',
        
        // Feedback & Complaints
        'feedback:submit' => 'Can submit feedback and complaints',
        'feedback:view_own' => 'Can view own submissions only',
        'feedback:track_status' => 'Can track complaint resolution status',
        
        // Transparency
        'transparency:view' => 'Can view transparency dashboard',
        'transparency:view_budget' => 'Can view overall budget allocation',
        'transparency:view_expenses' => 'Can view expense breakdowns',
        
        // Account
        'account:manage_profile' => 'Can update own profile information',
        'account:view_verification' => 'Can view verification status',
    ],
    
    'restrictions' => [
        // Cannot create or modify
        'projects:create' => false,
        'projects:edit' => false,
        'projects:delete' => false,
        'projects:assign' => false,
        
        // Cannot access contractor/engineer data
        'contractor:view_all' => false,
        'engineer:view_all' => false,
        'bac:view_all' => false,
        
        // Cannot manage other accounts
        'users:manage' => false,
        'users:delete' => false,
        'users:assign_role' => false,
        
        // Cannot view system logs
        'logs:view' => false,
        'audit:view' => false,
    ],
    
    'dashboard_features' => [
        'kpi_cards' => [
            'active_projects',
            'completed_projects',
            'delayed_projects',
            'my_submissions'
        ],
        'pages' => [
            'dashboard' => 'Main dashboard overview',
            'projects' => 'Public project listing',
            'project_status' => 'Detailed project tracking',
            'submit_feedback' => 'Feedback submission form',
            'track_feedback' => 'Complaint resolution tracking',
            'transparency' => 'Budget and expense transparency'
        ]
    ],
    
    'data_visibility' => [
        'own_feedback' => true,
        'all_projects' => true,
        'project_budgets' => true,
        'project_expenses' => true,
        'other_citizens_feedback' => false,
        'contractor_details' => false,
        'engineer_assignments' => false,
        'internal_notes' => false,
        'payment_data' => false,
    ],
    
    'requirements' => [
        'must_verify_identity' => true,
        'id_photo_required' => true,
        'admin_approval_required' => true,
        'minimum_age' => 18,
    ],
    
    'api_endpoints' => [
        '/citizen/api/dashboard.php' => 'GET - Dashboard statistics',
        '/citizen/api/projects.php' => 'GET - Projects listing with filters',
        '/citizen/api/project-status.php' => 'GET - Detailed project status',
        '/citizen/api/my-feedback.php' => 'GET - User feedback history',
        '/citizen/api/submit-feedback.php' => 'POST - Submit feedback',
        '/citizen/api/transparency.php' => 'GET - Transparency dashboard',
    ]
];

// Helper function to check citizen permission
function citizenHasPermission($permission) {
    global $CITIZEN_SCOPE;
    return isset($CITIZEN_SCOPE['permissions'][$permission]) 
        && $CITIZEN_SCOPE['permissions'][$permission];
}

// Helper function to check citizen restriction
function citizenRestricted($action) {
    global $CITIZEN_SCOPE;
    return isset($CITIZEN_SCOPE['restrictions'][$action]) 
        && !$CITIZEN_SCOPE['restrictions'][$action];
}
