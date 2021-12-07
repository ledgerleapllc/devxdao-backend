<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProposalDraft extends Model
{
    protected $table = 'proposal_draft';

    protected $guarded = [];
    protected $casts = [
        'members' => 'array',
        'grants' => 'array',
        'milestones' => 'array',
        'citations' => 'array',
        'tags' => 'array',
    ];
    public function files()
    {
        return $this->hasMany('App\ProposalDraftFile', 'proposal_draft_id');
    }
}
