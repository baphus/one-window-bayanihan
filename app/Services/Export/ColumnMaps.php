<?php

namespace App\Services\Export;

class ColumnMaps
{
    private static array $maps = [
        'cases' => [
            ['key' => 'id',                        'label' => 'ID',                          'type' => 'uuid'],
            ['key' => 'case_number',               'label' => 'Case Number',                 'type' => 'string'],
            ['key' => 'client_type',               'label' => 'Intake Type',                 'type' => 'string'],
            ['key' => 'vulnerability_indicator',   'label' => 'Vulnerability Indicator',     'type' => 'string'],
            ['key' => 'nok_vulnerability_indicator', 'label' => 'NOK Vulnerability Indicator', 'type' => 'string'],
            ['key' => 'tracker_number',            'label' => 'Tracker Number',              'type' => 'string'],
            ['key' => 'summary',                   'label' => 'Summary',                     'type' => 'string'],
            ['key' => 'status',                    'label' => 'Status',                      'type' => 'status'],
            ['key' => 'closed_at',                 'label' => 'Closed At',                   'type' => 'date'],
            ['key' => 'consent_given_at',          'label' => 'Consent Given At',            'type' => 'date'],
            ['key' => 'user_id',                   'label' => 'User ID',                     'type' => 'uuid'],
            ['key' => 'ofw_user_id', 'label' => 'Linked OFW Account ID', 'type' => 'uuid'],
            ['key' => 'categories',                'label' => 'Categories',                  'type' => 'string'],
            ['key' => 'created_at',                'label' => 'Created At',                  'type' => 'date'],
            ['key' => 'updated_at',                'label' => 'Updated At',                  'type' => 'date'],
        ],

        'clients' => [
            ['key' => 'id',             'label' => 'Case Record ID',  'type' => 'uuid'],
            ['key' => 'case_number',    'label' => 'Case Number',     'type' => 'string'],
            ['key' => 'first_name',     'label' => 'First Name',     'type' => 'string'],
            ['key' => 'last_name',      'label' => 'Last Name',      'type' => 'string'],
            ['key' => 'middle_name',    'label' => 'Middle Initial',    'type' => 'string'],
            ['key' => 'suffix',         'label' => 'Suffix',         'type' => 'string'],
            ['key' => 'date_of_birth',  'label' => 'Date of Birth',  'type' => 'date'],
            ['key' => 'sex',            'label' => 'Sex',            'type' => 'string'],
            ['key' => 'email',          'label' => 'Email',          'type' => 'string'],
            ['key' => 'contact_number', 'label' => 'Contact Number', 'type' => 'string'],
            ['key' => 'ofw_user_id',    'label' => 'Linked OFW Account ID', 'type' => 'uuid'],
            ['key' => 'created_at',     'label' => 'Created At',     'type' => 'date'],
            ['key' => 'updated_at',     'label' => 'Updated At',     'type' => 'date'],
        ],

        'referrals' => [
            ['key' => 'id', 'label' => 'ID', 'type' => 'uuid'],
            ['key' => 'case_number', 'label' => 'Case Number', 'type' => 'string'],
            ['key' => 'referred_agency', 'label' => 'Referred Agency', 'type' => 'string'],
            ['key' => 'category', 'label' => 'Referral Category', 'type' => 'string'],
            ['key' => 'issue_concern', 'label' => 'Concern', 'type' => 'string'],
            ['key' => 'override_reason', 'label' => 'Routing Override Reason', 'type' => 'string'],
            ['key' => 'client_type', 'label' => 'Intake Type', 'type' => 'string'],
            ['key' => 'client_full_name', 'label' => 'OFW Name', 'type' => 'string'],
            ['key' => 'client_age', 'label' => 'Age at Intake', 'type' => 'int'],
            ['key' => 'client_full_address', 'label' => 'Case Address', 'type' => 'string'],
            ['key' => 'required_services', 'label' => 'Required Services', 'type' => 'string'],
            ['key' => 'referral_status', 'label' => 'Status', 'type' => 'status'],
            ['key' => 'date_referred', 'label' => 'Date Referred', 'type' => 'date'],
            ['key' => 'latest_update', 'label' => 'Latest Update', 'type' => 'string'],
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
            ['key' => 'case_id', 'label' => 'Case ID', 'type' => 'uuid'],
            ['key' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['key' => 'relationship', 'label' => 'Relationship', 'type' => 'string'],
            ['key' => 'phone_number', 'label' => 'Phone Number', 'type' => 'string'],
            ['key' => 'email', 'label' => 'Email', 'type' => 'string'],
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
            ['key' => 'case_id',         'label' => 'Case ID',         'type' => 'uuid'],
            ['key' => 'region',            'label' => 'Region',            'type' => 'string'],
            ['key' => 'province',          'label' => 'Province',          'type' => 'string'],
            ['key' => 'city_municipality', 'label' => 'City/Municipality', 'type' => 'string'],
            ['key' => 'barangay',          'label' => 'Barangay',          'type' => 'string'],
            ['key' => 'street',            'label' => 'Street',            'type' => 'string'],
        ],

        'client_employments' => [
            ['key' => 'case_id',       'label' => 'Case ID',       'type' => 'uuid'],
            ['key' => 'employer_name',   'label' => 'Employer Name',   'type' => 'string'],
            ['key' => 'position',        'label' => 'Occupation',        'type' => 'string'],
            ['key' => 'last_position',   'label' => 'Last Occupation',   'type' => 'string'],
            ['key' => 'country',         'label' => 'Country',         'type' => 'string'],
            ['key' => 'last_country',    'label' => 'Last Country',    'type' => 'string'],
            ['key' => 'start_date',      'label' => 'Start Date',      'type' => 'date'],
            ['key' => 'end_date',        'label' => 'End Date',        'type' => 'date'],
            ['key' => 'date_of_arrival', 'label' => 'Date of Arrival', 'type' => 'date'],
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
