<table>
    <thead>

    </thead>
    <tbody>
        <tr>
            <td>Proposal number</td>
            <td>Vote Type</td>
            <td>Vote stage</td>
            <td>Stake for/against</td>
            <td>Date Vote</td>
            @foreach($users as $user)
            <td>{{ $user->forum_name }}</td>
            @endforeach
        </tr>
        @foreach($votes as $vote)
        <tr>
            <td>{{ $vote->proposal_id }}</td>
            <td>{{ $vote->content_type }}</td>
            <td>{{ $vote->type}}</td>
            <td>{{ $vote->for_value .'/' .$vote->against_value }}</td>
            <td>{{ $vote->created_at}}</td>
            @foreach($vote->responseVotes as $response)
            @if($response['type'] == 'for')
            <td>{{ $response['value'] }}</td>
            @elseif($response['type'] == 'against')
            <td>  {{ - $response['value']}} </td>
            @else
            <td> {{$response['type']}}</td>
            @endif
            @endforeach
        </tr>
        @endforeach

    </tbody>
</table>