<!DOCTYPE html>
<html>
<style>
    * {
        font-size: 14px;
        margin: 0;
        padding: 0;
    }

    header .header {
        height: 70px;
        width: 100vw;
        background-color: #743fd1;
    }

    header .separator {
        width: 0;
        height: 0;
        border-style: solid;
        border-width: 70px 703px 0 0;
        border-color: #743fd1 #fafafa transparent transparent;
    }

    .mt-60 {
        margin-top: 60px;
    }

    h2 {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 15px;
    }

    .main-content {
        padding: 60px;
        background-color: #fafafa;
    }
    .page-break {
        page-break-after: always;
    }

    .tbl {
        background-color: #000;
        width: 100%;
    }

    .tbl td,
    th,
    caption {
        background-color: #fff
    }

    .tbl td {
        padding-left: 20px;
    }

    .row::after {
        /* display: flex; */
        display: block;
        clear: both;
        content: "";
    }

    .col-5 {
        width: 41.6%;
        float: left;
    }

    .col-7 {
        width: 58.4%;
        float: left;
    }
</style>

<body>
    <div>
        <div class="content">
            <header>
                <div class="header"></div>
                <div class="separator"></div>
            </header>
            <div class="main-content">
                <div>
                    <h2>Payee Details</h2>
                    <div class="row">
                        <div class="col-5">
                            <p>User Email:</p>
                        </div>
                        <div class="col-7">
                            <p>{{$invoice->user->email}}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-5">
                            <p>Name:</p>
                        </div>
                        <div class="col-7">
                            <p>{{$invoice->user->first_name}} {{$invoice->user->first_name}}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-5">
                            <p>Company</p>
                        </div>
                        <div class="col-7">
                            <p>{{$invoice->user->profile->company}}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-5">
                            <p>Shufti Ref Number:</p>
                        </div>
                        <div class="col-7">
                            <p>{{$invoice->user->shuftipro->reference_id}}</p>
                        </div>
                    </div>
                </div>
                <div class="mt-60">
                    <h2>Payment Details</h2>
                    <div class="row">
                        <div class="col-5">
                            <p>Invoice number:</p>
                        </div>
                        <div class="col-7">
                            <p>{{$invoice->id}}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-5">
                            <p>Invoice date:</p>
                        </div>
                        <div class="col-7">
                            <p>{{ $vote->updated_at->format('d/m/Y H:i A') }}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-5">
                            <p>Grant Number:</p>
                        </div>
                        <div class="col-7">
                            <p>{{$invoice->proposal_id}}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-5">
                            <p>Grant Link:</p>
                        </div>
                        <div class="col-7">
                            <p>{{$invoice->public_grant_url}}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-5">
                            <p>Milestone Number:</p>
                        </div>
                        <div class="col-7">
                            <p>{{$invoice->milestone_number}}</p>
                        </div>
                    </div>
                </div>
                <div class="mt-60">
                    <h2>Vote Details</h2>
                    <div class="row">
                        <div class="col-5">
                            <p>Date Formal Vote Completed:</p>
                        </div>
                        <div class="col-7">
                            <p>{{ $vote->updated_at->format('d/m/Y') }}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-5">
                            <p>Vote Obtained:</p>
                        </div>
                        <div class="col-7">
                            <p>{{$vote->results->count()}}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-5">
                            <p>Result:</p>
                        </div>
                        <div class="col-7">
                            <p class="text-green">PASS</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-5">
                            <p>Stake For/Against:</p>
                        </div>
                        <div class="col-7">
                            <p class="text-green">PA{{ $vote->results->where('type', 'for')->count() }} / {{ $vote->results->where('type', 'against')->count() }}SS</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="page-break"></div>

        <div class="content">
            <h2>Vote detail table.</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th><strong> Forum Name </strong></th>
                        <th><strong> Stake For </strong></th>
                        <th><strong> Stake Against </strong></th>
                        <th><strong> Time Of Vote </strong></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vote->results as $voteResults)
                    <tr>
                        <td> {{ $voteResults->forum_name }} </td>
                        <td> {{ $voteResults->type == 'for' ? $voteResults->value : '' }} </td>
                        <td> {{ $voteResults->type == 'against' ? $voteResults->value : '' }} </td>
                        <td> {{ $voteResults->created_at->format('m-d-Y  H:i A')}} </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>