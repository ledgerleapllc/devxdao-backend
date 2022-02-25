<!DOCTYPE html>
<html>
<style>
    * {
        font-size: 12px;
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
    .page-break {
        page-break-after: always;
    }
</style>
<body>
    <div>
        <div class="content">
            <table class="tbl">
                <tr>
                    <td><span style="font-weight: 600;">Proposal number: </span></td>
                    <td><p>{{$proposal->id}}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Proposal Type: </span></td>
                    <td><p>{{ ucfirst($proposal->type) }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Proposal Title: </span></td>
                    <td><p>{{  $proposal->title }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;"> Proposal summary preview:</span></td>
                    <td><p>{{ $proposal->summary_preview }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;"> Proposal vote type:</span></td>
                    <td><p>{{  ucfirst($proposal->vote->content_type) }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;"> Milestone number:</span></td>
                    <td><p>{{  $proposal->milestone->milestonePosition ?? 'None' }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Milestone title: </span></td>
                    <td><p>{{ $proposal->milestone->title ?? 'None' }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Milestone detail </span></td>
                    <td><p>{{ $proposal->milestone->details ?? 'None' }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Vote phase: </span></td>
                    <td><p>{{ ucfirst($proposal->vote->type) }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;"> Result</span></td>
                    <td><p>{{ ucfirst($proposal->vote->result) }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;">Stake for/against: </span></td>
                    <td><p>{{ $proposal->voteResults->where('type', 'for')->count() }} / {{ $proposal->voteResults->where('type', 'against')->count() }}</p></td>
                </tr>
                <tr>
                    <td><span style="font-weight: 600;"> Vote end time:</span></td>
                    <td><p>{{ $proposal->vote->timeLeft->format('m-d-Y  H:i A') }}</p></td>
                </tr>
            </table>
        </div>
        <div class="page-break"></div>
        <div class="content">
            <h1>Vote detail table.</h1>
            <table class="tbl">
                <thead>
                    <tr>
                        <th><strong> Forum Name </strong></th>
                        <th><strong> Stake For </strong></th>
                        <th><strong> Stake Against </strong></th>
                        <th><strong> Time Of Vote </strong></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($proposal->voteResults as $result)
                    <tr>
                        <td><p> {{ $result->forum_name }} </p></td>
                        <td><p> {{ $result->type == 'for' ? $result->value : '' }} </p></td>
                        <td><p> {{ $result->type == 'against' ? $result->value : '' }} </p></td>
                        <td><p> {{ $result->created_at->format('m-d-Y  H:i A')}} </p></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
