<?php

namespace App\Services;

use App\Http\Helper;
use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProposalService
{
    public function withAttestation(Collection $proposals)
    {
        $topicIds = $proposals->pluck('discourse_topic_id')->unique();

        $attestationRates = DB::table('topic_reads')
            ->select('topic_id', DB::raw('count(*) as count'))
            ->whereIn('topic_id', $topicIds)
            ->groupBy('topic_id')
            ->get();

        $attestationUsers = DB::table('topic_reads')
            ->select('topic_id', 'user_id')
            ->whereIn('topic_id', $topicIds)
            ->get();

        $VACount = User::where('is_member', true)->count();

        foreach ($proposals as $key => $proposal) {
            $count = $attestationRates->firstWhere('topic_id', $proposal->discourse_topic_id)->count ?? 0;

            $proposals[$key]['attestation'] = [
                'rate' => $count / $VACount * 100,
                'is_attestated' => $attestationUsers
                    ->where('user_id', Auth::id())
                    ->where('topic_id', $proposal->discourse_topic_id)
                    ->isNotEmpty(),
            ];
        }

        return $proposals;
    }

    public function withStatusLabel(Collection $proposals)
    {
        return $proposals->map(function ($proposal) {
            $proposal->status_label = Helper::getStatusProposal($proposal);

            return $proposal;
        });
    }
}
