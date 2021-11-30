<!DOCTYPE html>
<html>
<style>
    * {
        font-size: 9px;
    }
    .tbl {background-color:#000; width: 100%;}
    .tbl td,th,caption{background-color:#fff}
    .tbl td {
        padding-left: 20px;
    }
    p {
        white-space: pre-wrap;
        word-wrap: break-word;
    }
</style>
<body>
    <div>
        <div class="content">
            <table cellspacing="1" class="tbl">
                <tr>
                    <td><span style="font-weight: 600;">Proposal Number: </span></td>
                    <td><p>{{$proposal->id}}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Proposal Type: </span></td>
                    <td><p>{{$proposal->type}}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">User Id: </span></td>
                    <td><p>{{$proposal->user_id }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">OP: </span></td>
                    <td><p>{{$proposal->user->profile->forum_name ?? ''}}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Sponsor: </span></td>
                    <td><p>{{$proposal->sponsor->profile->forum_name ?? ''}}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Rep Staked:: </span></td>
                    <td><p>{{$proposal->rep ?? ''}}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Proposal Status: </span></td>
                    <td>
                        @switch($proposal->status)
                            @case('pending')
                                <p>{{ 'Pending' }}</p>
                                @break
                            @case('payment')
                                @if ($proposal->dos_paid)
                                    <p>{{ 'Payment Clearing' }}</p>
                                @else
                                    <p>{{ 'Payment Waiting' }}</p>
                                @endif
                                @break
                            @case('denied')
                                <p>{{ 'Denied' }}</p>
                                @break
                            @case('completed')
                                <p>{{ 'Completed' }}</p>
                                @break
                            @case('approved')
                                @if ($proposal->votes && count($proposal->votes))
                                    @if (count($proposal->votes) > 1)
                                        @if ($proposal->votes[1]->status == 'active')
                                            <p>{{ 'Formal Voting Live' }}</p>
                                        @else
                                            @if ($proposal->votes[1]->result == 'success')
                                                <p>{{ 'Formal Voting Passed' }}</p>
                                            @elseif ($proposal->votes[1]->result == 'no-quorum')
                                                <p>{{ 'Formal Voting No Quorum' }}</p>
                                            @else
                                                <p>{{ 'Formal Voting Failed' }}</p>
                                            @endif
                                        @endif
                                    @else
                                        @if ($proposal->votes[0]->status == 'active')
                                            <p>{{ 'Informal Voting Live' }}</p>
                                        @else
                                            @if ($proposal->votes[0]->result == 'success')
                                                <p>{{ 'Informal Voting Passed' }}</p>
                                            @elseif ($proposal->votes[0]->result == 'no-quorum')
                                                <p>{{ 'Informal Voting No Quorum' }}</p>
                                            @else
                                                <p>{{ 'Informal Voting Failed' }}</p>
                                            @endif
                                        @endif
                                    @endif
                                @else
                                    <p>{{ 'In Discussion' }}</p>
                                @endif
                                @break
                            @default
                                <p>{{ '' }}</p>
                        @endswitch
                    </td>
                </tr>
                @if ($proposal->winner)
                    <tr>
                        <td><span style="font-weight: 600;">Survey won: </span></td>
                        <td><p>{{$proposal->winner->survey_id ?? '-'}}</p></td>
                    </tr>
                    <tr>
                        <td><span style="font-weight: 600;">Survey rank: </span></td>
                        <td><p>{{implode('/', array_filter([
                            $proposal->winner->rank ?? '',
                            $proposal->winner->survey->number_response ?? ''
                        ]))}}</p></td>
                    </tr>
                @endif
                @if ($proposal->loser)
                    <tr>
                        <td><span style="font-weight: 600;">Survey Lost: </span></td>
                        <td><p>{{$proposal->loser->survey_id ?? '-'}}</p></td>
                    </tr>
                    <tr>
                        <td><span style="font-weight: 600;">Downvote rank: </span></td>
                        <td><p>{{implode('/', array_filter([
                            $proposal->loser->rank ?? '',
                            $proposal->loser->survey->number_response ?? ''
                        ]))}}</p></td>
                    </tr>
                @endif
                <tr>
                    <td><span style="font-weight: 600;">Title: </span></td>
                    <td><p>{{$proposal->title}}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Short Description: </span></td>
                    <td><p>{!! \Illuminate\Support\Str::limit($proposal->short_description, 600) !!}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Explanation Benefit: </span></td>
                    <td><p>{!! $proposal->explanation_benefit !!}</p></td>
                </tr>
                <!-- <tr>
                    <td><span style="font-weight: 600;">Explanation Goal: </span></td>
                    <td><p>{{$proposal->explanation_goal }}</p></td>
                </tr> -->
                <tr>
                    <td><span style="font-weight: 600;">Total Grant: </span></td>
                    <td><p>{{$proposal->total_grant }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">License: </span></td>
                    <td>
                        @switch($proposal->license)
                            @case(0)
                                <p>{{ 'MIT License' }}</p>
                                @break
                            @case(1)
                                <p>{{ 'Apache License 2.0' }}</p>
                                @break
                            @case(2)
                                <p>{{ 'BSD License' }}</p>
                                @break
                            @case(3)
                                <p>{{ 'GPL License' }}</p>
                                @break
                            @case(4)
                                <p>{{ 'MPL-2.0 License' }}</p>
                                @break
                            @case(6)
                                <p>{{ 'Creative commons (for research and documents only)' }}</p>
                                @break
                            @case(5)
                                <p>{{ 'Other' }}</p>
                                @break
                            @default
                        @endswitch
                    </td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">License Other: </span></td>
                    <td><p>{{$proposal->license_other }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Resume: </span></td>
                    <td><p>{!! $proposal->resume !!}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Extra Notes: </span></td>
                    <td><p>{!! $proposal->extra_notes !!}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Relationship: </span></td>
                    <td>@switch($proposal->relationship)
                        @case(0)
                            <p>{{ "I am affiliated with the ETA or a sponsor to the ETA" }}</p>
                            @break
                        @case(1)
                            <p>{{ "I am a Contributor to the ETA." }}</p>
                            @break
                        @case(2)
                            <p>{{ "My Project Plan exclusively supports the business and/or activities of a Contributor of ETA." }}</p>
                            @break
                        @case(3)
                            <p>{{ "I have a close relationship with a Contributor of ETA and my Project Plan largely supports the business and/or activities of that Contributor." }}</p>
                            @break
                        @case(4)
                            <p>{{ "I am a director, officer, or employee of the ETA." }}</p>
                            @break
                        @case(5)
                            <p>{{ "None of the above" }}</p>
                            @break
                        @default
                    @endswitch</td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Grant Id: </span></td>
                    <td><p>{{$proposal->grant_id }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Received Grant Before: </span></td>
                    <td><p>{{$proposal->received_grant_before == 1 ? 'Yes' : 'No' }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Has Fulfilled: </span></td>
                    <td><p>{{$proposal->has_fulfilled == 1 ? 'Yes' : 'No' }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Received Grant: </span></td>
                    <td><p>{{$proposal->received_grant == 1 ? 'Yes' : 'No' }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Grant funds: </span></td>
                    <td>
                        @foreach(($proposal->grants ?? []) as $key=>$grant)
                            @switch($grant->type)
                                @case(0)
                                    <p>{{ 'Salary and other personal compensation' }} {{ $grant->grant }}</p>
                                    @break
                                @case(1)
                                    <p>{{ 'Travel and conferences' }} {{ $grant->grant }}</p>
                                    @break
                                @case(2)
                                    <p>{{ 'Software, tools, infrastructure' }} {{ $grant->grant }}</p>
                                    @break
                                @case(3)
                                    <p>{{ 'Legal, accounting, recruiting' }} {{ $grant->grant }}</p>
                                    @break
                                @case(4)
                                    <p>{{ 'Other' }} {{ $grant->grant }}</p>
                                    @break
                                @default
                                    <p>{{ 'Other' }} {{ $grant->grant }}</p>
                            @endswitch
                            @if ($grant->type_other)
                                <p>{{ $grant->type_other }}</p>
                            @endif
                            <p>Percentage kept by OP: {{ $grant->percentage ?? 0 }}%</p>
                        @endforeach
                    </td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Has Mentor: </span></td>
                    <td><p>{{$proposal->have_mentor == 1 ? 'Yes' : 'No' }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Mentor Name: </span></td>
                    <td><p>{{$proposal->name_mentor }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Mentor Hours: </span></td>
                    <td><p>{{$proposal->total_hours_mentor }}</p></td>
                </tr>

                <tr>
                    <td><span style="font-weight: 600;">Company or Organization: </span></td>
                    <td><p>{{$proposal->is_company_or_organization == 1 ? 'Yes' : 'No' }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Entity Name: </span></td>
                    <td><p>{{$proposal->name_entity }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Entity Country: </span></td>
                    <td><p>{{$proposal->entity_country }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Team Members: </span></td>
                    <td>@foreach(($proposal->teams ?? []) as $key=>$team)
                        <p>Team Member {{$key + 1}} : {{$team->full_name }}</p>
                        @endforeach
                    </td>
                </tr>
                @foreach(($proposal->citations ?? []) as $key=>$citation)
                <tr>
                    <td><span style="font-weight: 600;">Citation #{{$key + 1}}</span></td>
                    <td>
                        <p>Cited Proposal Number: {{ $citation->rep_proposal_id }}</p>
                        <p>Cited Proposal Title: {{ $citation->rep_proposal->title ?? '' }}</p>
                        <p>Cited Proposal OP: {{ $citation->rep_proposal->user->profile->forum_name ?? '' }}</p>
                        <p>Explain how this work is foundational to your work: {{ $citation->explanation }}</p>
                        <p>% of the rep gained from this proposal do you wish to give to the OP of the prior work: {{ $citation->percentage }}</p>
                    </td>
                </tr>
                @endforeach
                @foreach(($proposal->milestones ?? []) as $key=>$milestone)
                <tr>
                    <td><span style="font-weight: 600;">Milestone #{{$key + 1}}</span></td>
                    <td>
                        <p>Milestone title: {{$milestone->title}}</p>
                        <p>The portion that the OP is requesting from the total grant for the milestone: {{$milestone->grant}}</p>
                        <p>Due date: {{$milestone->deadline}}</p>
                        <p>Details of what will be delivered: </p><p>{!! $milestone->details !!}</p>
                        <p>Acceptance Criteria: </p><p>{!! $milestone->criteria !!}</p>
                    </td>
                </tr>
                @endforeach
                <tr>
                    <td><span style="font-weight: 600;">KYC Status: </span></td>
                    <td>
                        @if ($proposal->user->shuftipro ?? null)
                            @if (($proposal->user->shuftipro->status ?? null) == "approved")
                                <p>{{ 'Pass' }}</p>
                            @else
                                <p>{{ 'Fail' }}</p>
                            @endif
                        @else
                            <p>{{ 'Need to Submit' }}</p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Tags: </span></td>
                    <td><p>{{ $proposal->tags }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Uploaded Files: </span></td>
                    <td>
                        @foreach(($proposal->files ?? []) as $key=>$file)
                            <p>{{ $file->name }}</p>
                        @endforeach
                    </td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Compliance Check: </span></td>
                    <td>
                        @if (($proposal->onboarding->force_to_formal ?? null) &&
                            ($proposal->onboarding->compliance_status ?? null) == "approved")
                            <p>Status: {{ "manually approved" }}</p>
                        @else
                            <p>Status: {{ $proposal->onboarding->compliance_status ?? '' }}</p>
                        @endif
                        <p>Admin email: {{ $proposal->onboarding->admin_email ?? '' }}</p>
                        <p>Timestamp: {{
                            ($proposal->onboarding->compliance_reviewed_at ?? null)
                            ? (new Carbon\Carbon($proposal->onboarding->compliance_reviewed_at))->format('H:i m-d-Y')
                            : ''
                        }}</p>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>

</html>
