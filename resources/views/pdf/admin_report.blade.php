<!DOCTYPE html>
<html>
<style>
    * {
        font-size: 12px;
    }

    #onboarding {
        font-family: Arial, Helvetica, sans-serif;
        border-collapse: collapse;
        width: 100%;
    }

    #onboarding td,
    #onboarding th {
        border: 1px solid #ddd;
        padding: 8px;
    }

    #onboarding tr:nth-child(even) {
        background-color: #f2f2f2;
    }

    #onboarding tr:hover {
        background-color: #ddd;
    }

    #onboarding th {
        padding-top: 12px;
        padding-bottom: 12px;
        text-align: left;
        background-color: white;
        color: black;
    }

    .page-break {
        page-break-after: always;
    }
</style>

<body>
    @foreach ($response as $report)
    <div>
        <div class="content">
            <h2 style="font-size: 16px;"> Onboarding Stats in {{ $report['year'] }}</h2>
            <table id="onboarding">
                <thead>
                    <tr>
                        <th><strong> Months </strong></th>
                        <th><strong> Number Onboarded </strong></th>
                        <th><strong> Total </strong></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['onboarding_results'] as $item)
                    <tr>
                        <td> {{ $item['month'] }} </td>
                        <td> {{ $item['number_onboarded'] }} </td>
                        <td> {{ $item['total'] }} </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="content">
            <h2 style="font-size: 16px;"> Reputation by User in {{ $report['year'] }} </h2>
            <table id="onboarding">
                <thead>
                    <tr>
                        <th><strong> User </strong></th>
                        <th><strong> Jan </strong></th>
                        <th><strong> Feb </strong></th>
                        <th><strong> Mar </strong></th>
                        <th><strong> Apr </strong></th>
                        <th><strong> May </strong></th>
                        <th><strong> June </strong></th>
                        <th><strong> Jul </strong></th>
                        <th><strong> Aug </strong></th>
                        <th><strong> Sep </strong></th>
                        <th><strong> Oct </strong></th>
                        <th><strong> Nov </strong></th>
                        <th><strong> Dec </strong></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['reputation_results'] as $item)
                    <tr>
                        <td> {{ $item['is_member'] == 1 ?  $item['username'] : $item['username'] . " * " }} </td>
                        @foreach ($item['rep_results'] as $value)
                        <td> {{ $value['total'] > 0 ? number_format($value['total'], 3) : 0}} rep </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="content">
            <h2 style="font-size: 16px;"> Relative Voting Weights in {{ $report['year'] }}</h2>
            <table id="onboarding">
                <thead>
                    <tr>
                        <th><strong> User </strong></th>
                        <th><strong> Total Rep </strong></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['total_reputation_results'] as $item)
                    <tr>
                        <td> {{ $item['is_member'] == 1 ?  $item['username'] : $item['username'] . " * " }} </td>
                        <td> {{ $item['total_rep'] }} </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
</body>

</html>