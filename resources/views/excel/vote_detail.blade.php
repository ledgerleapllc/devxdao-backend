<table>
    <thead>

    </thead>
    <tbody>
        <tr>
            <td>Proposal number:</td>
            <td>{{ $proposal->id }}</td>
        </tr>
        <tr>
            <td>Proposal Type:</td>
            <td>{{ ucfirst($proposal->type) }}</td>
        </tr>
        <tr>
            <td>Proposal Title:</td>
        </tr>
        <tr>
            <td>{{ $proposal->title }}</td>
        </tr>
        <tr>
            <td></td>
        </tr>
        <tr>
            <td>Proposal summary preview:</td>
        </tr>
        <tr>
            <td>{{ $proposal->summary_preview }}</td>
        </tr>
        <tr>
            <td>Proposal vote type:</td>
            <td>{{ ucfirst($proposal->vote->content_type) }}</td>
        </tr>
        <tr>
            <td>Milestone number:</td>
            <td>{{ $proposal->milestone->milestonePosition ?? 'None' }}</td>
        </tr>
        <tr>
            <td>Milestone title:</td>
            <td>{{ $proposal->milestone->title ?? 'None' }}</td>
        </tr>
        <tr>
            <td>Milestone detail:</td>
        </tr>
        <tr>
            <td>{{ $proposal->milestone->details ?? 'None' }}</td>
        </tr>
        <tr>
            <td>Vote phase:</td>
            <td>{{ ucfirst($proposal->vote->type) }}</td>
        </tr>
        <tr>
            <td>Result:</td>
            <td>{{ ucfirst($proposal->vote->result) }}</td>
        </tr>

        <tr>
            <td>Stake for/against:</td>
            <td>{{ $proposal->voteResults->where('type', 'for')->count() }} / {{ $proposal->voteResults->where('type', 'against')->count() }}</td>
        </tr>
        <tr>
            <td>Vote end time:</td>
            <td>{{ $proposal->vote->timeLeft->format('m-d-Y  H:i A') }}</td>
        </tr>
        <tr>
            <td></td>
        </tr>
        <tr>
            <td></td>
        </tr>
        <tr>
            <td>Forum Name</td>
            <td>Stake For</td>
            <td>Stake Against</td>
            <td>Time of Vote</td>
        </tr>
        @foreach($proposal->voteResults as $result)
        <tr>
            <td>{{ $result->forum_name }}</td>
            <td>{{ $result->type == 'for' ? $result->value : '' }}</td>
            <td>{{ $result->type == 'against' ? $result->value : '' }}</td>
            <td>{{ $result->created_at->format('m-d-Y  H:i A') }}</td>
        </tr>
        @endforeach

    </tbody>
</table>