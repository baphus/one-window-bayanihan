<?php

namespace App\Services\Export;

class ColumnMaps
{
    private static array $maps = [
        'cases' => [
            ['key' => 'id',                        'label' => 'ID',                          'type' => 'uuid'],
            ['key' => 'case_number',               'label' => 'Case Number',                 'type' => 'string'],
            ['key' => 'client_type',               'label' => 'Client Type',                 'type' => 'string'],
            ['key' => 'vulnerability_indicator',   'label' => 'Vulnerability Indicator',     'type' => 'string'],
            ['key' => 'nok_vulnerability_indicator', 'label' => 'NOK Vulnerability Indicator', 'type' => 'string'],
            ['key' => 'tracker_number',            'label' => 'Tracker Number',              'type' => 'string'],
            ['key' => 'summary',                   'label' => 'Summary',                     'type' => 'string'],
            ['key' => 'status',                    'label' => 'Status',                      'type' => 'status'],
            ['key' => 'closed_at',                 'label' => 'Closed At',                   'type' => 'date'],
            ['key' => 'consent_given_at',          'label' => 'Consent Given At',            'type' => 'date'],
            ['key' => 'user_id',                   'label' => 'User ID',                     'type' => 'uuid'],
            ['key' => 'client_id',                 'label' => 'Client ID',                   'type' => 'uuid'],
            ['key' => 'category_id',               'label' => 'Category ID',                 'type' => 'uuid'],
            ['key' => 'created_at',                'label' => 'Created At',                  'type' => 'date'],
            ['key' => 'updated_at',                'label' => 'Updated At',                  'type' => 'date'],
        ],

        'clients' => [
            ['key' => 'id',             'label' => 'ID',             'type' => 'uuid'],
            ['key' => 'first_name',     'label' => 'First Name',     'type' => 'string'],
            ['key' => 'last_name',      'label' => 'Last Name',      'type' => 'string'],
            ['key' => 'middle_initial',    'label' => 'Middle Initial',    'type' => 'string'],
            ['key' => 'suffix',         'label' => 'Suffix',         'type' => 'string'],
            ['key' => 'date_of_birth',  'label' => 'Date of Birth',  'type' => 'date'],
            ['key' => 'sex',            'label' => 'Sex',            'type' => 'string'],
            ['key' => 'email',          'label' => 'Email',          'type' => 'string'],
            ['key' => 'contact_number', 'label' => 'Contact Number', 'type' => 'string'],
            ['key' => 'created_at',     'label' => 'Created At',     'type' => 'date'],
            ['key' => 'updated_at',     'label' => 'Updated At',     'type' => 'date'],
        ],

        'referrals' => [
            ['key' => 'id',               'label' => 'ID',               'type' => 'uuid'],
            ['key' => 'required_services', 'label' => 'Required Services', 'type' => 'string'],
            ['key' => 'notes',            'label' => 'Notes',            'type' => 'string'],
            ['key' => 'status',           'label' => 'Status',           'type' => 'status'],
            ['key' => 'decision',         'label' => 'Decision',         'type' => 'string'],
            ['key' => 'decision_comment', 'label' => 'Decision Comment', 'type' => 'string'],
            ['key' => 'case_id',          'label' => 'Case ID',          'type' => 'uuid'],
            ['key' => 'agcy_id',          'label' => 'Agency ID',        'type' => 'uuid'],
            ['key' => 'type',             'label' => 'Type',             'type' => 'string'],
            ['key' => 'created_at',       'label' => 'Created At',       'type' => 'date'],
            ['key' => 'updated_at',       'label' => 'Updated At',       'type' => 'date'],
        ],

        'users' => [
            ['key' => 'id',              'label' => 'ID',              'type' => 'uuid'],
            ['key' => 'name',            'label' => 'Name',            'type' => 'string'],
            ['key' => 'email',           'label' => 'Email',           'type' => 'string'],
            ['key' => 'role',            'label' => 'Role',            'type' => 'string'],
            ['key' => 'agcy_id',         'label' => 'Agency ID',       'type' => 'uuid'],
            ['key' => 'is_active',       'label' => 'Is Active',       'type' => 'string'],
            ['key' => 'contact_number',  'label' => 'Contact Number',  'type' => 'string'],
            ['key' => 'position',        'label' => 'Position',        'type' => 'string'],
            ['key' => 'department',      'label' => 'Department',      'type' => 'string'],
            ['key' => 'office_location', 'label' => 'Office Location', 'type' => 'string'],
            ['key' => 'created_at',      'label' => 'Created At',      'type' => 'date'],
            ['key' => 'updated_at',      'label' => 'Updated At',      'type' => 'date'],
        ],

        'agencies' => [
            ['key' => 'id',           'label' => 'ID',           'type' => 'uuid'],
            ['key' => 'name',         'label' => 'Name',         'type' => 'string'],
            ['key' => 'short',        'label' => 'Short Name',   'type' => 'string'],
            ['key' => 'slug',         'label' => 'Slug',         'type' => 'string'],
            ['key' => 'description',  'label' => 'Description',  'type' => 'string'],
            ['key' => 'contact_info', 'label' => 'Contact Info', 'type' => 'string'],
            ['key' => 'is_active',    'label' => 'Is Active',    'type' => 'string'],
            ['key' => 'is_default',   'label' => 'Is Default',   'type' => 'string'],
            ['key' => 'created_at',   'label' => 'Created At',   'type' => 'date'],
            ['key' => 'updated_at',   'label' => 'Updated At',   'type' => 'date'],
        ],

        'services' => [
            ['key' => 'id',              'label' => 'ID',              'type' => 'uuid'],
            ['key' => 'name',            'label' => 'Name',            'type' => 'string'],
            ['key' => 'description',     'label' => 'Description',     'type' => 'string'],
            ['key' => 'processing_days', 'label' => 'Processing Days', 'type' => 'string'],
            ['key' => 'agcy_id',         'label' => 'Agency ID',       'type' => 'uuid'],
            ['key' => 'created_at',      'label' => 'Created At',      'type' => 'date'],
            ['key' => 'updated_at',      'label' => 'Updated At',      'type' => 'date'],
        ],

        'milestones' => [
            ['key' => 'id',          'label' => 'ID',          'type' => 'uuid'],
            ['key' => 'title',       'label' => 'Title',       'type' => 'string'],
            ['key' => 'description', 'label' => 'Description', 'type' => 'string'],
            ['key' => 'refr_id',     'label' => 'Referral ID', 'type' => 'uuid'],
            ['key' => 'user_id',     'label' => 'User ID',     'type' => 'uuid'],
            ['key' => 'created_at',  'label' => 'Created At',  'type' => 'date'],
            ['key' => 'updated_at',  'label' => 'Updated At',  'type' => 'date'],
        ],

        'next_of_kin' => [
            ['key' => 'id',                'label' => 'ID',                'type' => 'uuid'],
            ['key' => 'client_id',         'label' => 'Client ID',         'type' => 'uuid'],
            ['key' => 'first_name',        'label' => 'First Name',        'type' => 'string'],
            ['key' => 'middle_initial',    'label' => 'Middle Initial',    'type' => 'string'],
            ['key' => 'last_name',         'label' => 'Last Name',         'type' => 'string'],
            ['key' => 'is_primary',        'label' => 'Is Primary',        'type' => 'string'],
            ['key' => 'relationship',      'label' => 'Relationship',      'type' => 'string'],
            ['key' => 'phone_number',      'label' => 'Phone Number',      'type' => 'string'],
            ['key' => 'email',             'label' => 'Email',             'type' => 'string'],
            ['key' => 'full_address',      'label' => 'Full Address',      'type' => 'string'],
            ['key' => 'region',            'label' => 'Region',            'type' => 'string'],
            ['key' => 'province',          'label' => 'Province',          'type' => 'string'],
            ['key' => 'city_municipality', 'label' => 'City/Municipality', 'type' => 'string'],
            ['key' => 'barangay',          'label' => 'Barangay',          'type' => 'string'],
            ['key' => 'street',            'label' => 'Street',            'type' => 'string'],
            ['key' => 'sort_order',        'label' => 'Sort Order',        'type' => 'string'],
            ['key' => 'created_at',        'label' => 'Created At',        'type' => 'date'],
            ['key' => 'updated_at',        'label' => 'Updated At',        'type' => 'date'],
        ],

        'feedback' => [
            ['key' => 'id',                'label' => 'ID',                        'type' => 'uuid'],
            ['key' => 'case_id',           'label' => 'Case ID',                   'type' => 'uuid'],
            ['key' => 'referral_id',       'label' => 'Referral ID',               'type' => 'uuid'],
            ['key' => 'client_name',       'label' => 'Client Name',               'type' => 'string'],
            ['key' => 'agency_name',       'label' => 'Agency Name',               'type' => 'string'],
            ['key' => 'referral_status',   'label' => 'Referral Status',           'type' => 'string'],
            ['key' => 'service_name',      'label' => 'Service Name',              'type' => 'string'],
            ['key' => 'overall_rating',    'label' => 'Overall Rating',            'type' => 'string'],
            ['key' => 'tangibles_avg',     'label' => 'Tangibles Avg',             'type' => 'string'],
            ['key' => 'reliability_avg',   'label' => 'Reliability Avg',           'type' => 'string'],
            ['key' => 'responsiveness_avg', 'label' => 'Responsiveness Avg',        'type' => 'string'],
            ['key' => 'assurance_avg',     'label' => 'Assurance Avg',             'type' => 'string'],
            ['key' => 'empathy_avg',       'label' => 'Empathy Avg',               'type' => 'string'],
            ['key' => 'comments',          'label' => 'Comments',                  'type' => 'string'],
            ['key' => 'created_at',        'label' => 'Created At',                'type' => 'date'],
        ],

        'case_documents' => [
            ['key' => 'id',         'label' => 'ID',         'type' => 'uuid'],
            ['key' => 'file_name',  'label' => 'File Name',  'type' => 'string'],
            ['key' => 'file_path',  'label' => 'File Path',  'type' => 'string'],
            ['key' => 'file_type',  'label' => 'File Type',  'type' => 'string'],
            ['key' => 'case_id',    'label' => 'Case ID',    'type' => 'uuid'],
            ['key' => 'user_id',    'label' => 'User ID',    'type' => 'uuid'],
            ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ['key' => 'updated_at', 'label' => 'Updated At', 'type' => 'date'],
        ],

        'client_addresses' => [
            ['key' => 'id',                'label' => 'ID',                'type' => 'uuid'],
            ['key' => 'client_id',         'label' => 'Client ID',         'type' => 'uuid'],
            ['key' => 'region',            'label' => 'Region',            'type' => 'string'],
            ['key' => 'province',          'label' => 'Province',          'type' => 'string'],
            ['key' => 'city_municipality', 'label' => 'City/Municipality', 'type' => 'string'],
            ['key' => 'barangay',          'label' => 'Barangay',          'type' => 'string'],
            ['key' => 'street',            'label' => 'Street',            'type' => 'string'],
            ['key' => 'created_at',        'label' => 'Created At',        'type' => 'date'],
            ['key' => 'updated_at',        'label' => 'Updated At',        'type' => 'date'],
        ],

        'client_employments' => [
            ['key' => 'id',              'label' => 'ID',              'type' => 'uuid'],
            ['key' => 'client_id',       'label' => 'Client ID',       'type' => 'uuid'],
            ['key' => 'employer_name',   'label' => 'Employer Name',   'type' => 'string'],
            ['key' => 'position',        'label' => 'Position',        'type' => 'string'],
            ['key' => 'last_position',   'label' => 'Last Position',   'type' => 'string'],
            ['key' => 'country',         'label' => 'Country',         'type' => 'string'],
            ['key' => 'last_country',    'label' => 'Last Country',    'type' => 'string'],
            ['key' => 'start_date',      'label' => 'Start Date',      'type' => 'date'],
            ['key' => 'end_date',        'label' => 'End Date',        'type' => 'date'],
            ['key' => 'date_of_arrival', 'label' => 'Date of Arrival', 'type' => 'date'],
            ['key' => 'created_at',      'label' => 'Created At',      'type' => 'date'],
            ['key' => 'updated_at',      'label' => 'Updated At',      'type' => 'date'],
        ],

        'case_categories' => [
            ['key' => 'id',          'label' => 'ID',          'type' => 'uuid'],
            ['key' => 'name',        'label' => 'Name',        'type' => 'string'],
            ['key' => 'description', 'label' => 'Description', 'type' => 'string'],
            ['key' => 'color',       'label' => 'Color',       'type' => 'string'],
            ['key' => 'sort_order',  'label' => 'Sort Order',  'type' => 'string'],
            ['key' => 'is_active',   'label' => 'Is Active',   'type' => 'string'],
            ['key' => 'created_at',  'label' => 'Created At',  'type' => 'date'],
            ['key' => 'updated_at',  'label' => 'Updated At',  'type' => 'date'],
        ],

        'case_statuses' => [
            ['key' => 'id',         'label' => 'ID',         'type' => 'uuid'],
            ['key' => 'name',       'label' => 'Name',       'type' => 'string'],
            ['key' => 'slug',       'label' => 'Slug',       'type' => 'string'],
            ['key' => 'type',       'label' => 'Type',       'type' => 'string'],
            ['key' => 'color',      'label' => 'Color',      'type' => 'string'],
            ['key' => 'sort_order', 'label' => 'Sort Order', 'type' => 'string'],
            ['key' => 'is_system',  'label' => 'Is System',  'type' => 'string'],
            ['key' => 'is_active',  'label' => 'Is Active',  'type' => 'string'],
            ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
            ['key' => 'updated_at', 'label' => 'Updated At', 'type' => 'date'],
        ],
    ];

    public static function getMap(string $table): array
    {
        return self::$maps[$table] ?? [];
    }

    public static function getAllTables(): array
    {
        return array_keys(self::$maps);
    }
}
