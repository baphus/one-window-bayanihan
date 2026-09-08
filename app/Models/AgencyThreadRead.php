<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user "last read" marker for a private agency-to-agency thread.
 *
 * A thread is scoped to (case, agency pair): each Agency user tracks when
 * they last read their conversation with a given peer agency on a given case.
 *
 * The model uses a composite primary key (user_id, case_id, peer_agency_id);
 * reads and writes are handled via query builder (see ReferralMessageService)
 * because Eloquent's find/save paths assume a single-key model.
 */
class AgencyThreadRead extends Model
{
    protected $table = 'agency_thread_reads';

    public $incrementing = false;

    protected $primaryKey = ['user_id', 'case_id', 'peer_agency_id'];

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'case_id',
        'peer_agency_id',
        'last_read_at',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
    ];
}
