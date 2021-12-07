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
    <div>
        <div class="content">
            <h2 style="font-size: 14px;"> The progress of the onboarding</h2>
            <table id="onboarding">
                <thead>
                    <tr>
                        <th><strong> Months </strong></th>
                        <th><strong> Number of people </strong></th>
                        <th><strong> Total amount of grants </strong></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grant_results as $item)
                    <tr>
                        <td> {{ $item['month'] }} </td>
                        <td> {{ $item['number_onboarded'] }} onboarded </td>
                        <td> {{ $item['total'] }}k euro processed </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @foreach ($rep_results as $result)
        <div class="content">
            <h2 style="font-size: 14px;"> The progress of the onboarding report VA in {{$result['year']}}</h2>
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
                        <th><strong> Projected </strong></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($result['rep_response'] as $item)
                    <tr>
                        <td> {{ $item['user_id'] }} </td>
                        <td> {{ $item['rep_results']['month_1'] > 0 ? number_format($item['rep_results']['month_1'], 3) : 0}} rp </td>
                        <td> {{ $item['rep_results']['month_2'] > 0 ?  number_format($item['rep_results']['month_2'] , 3) : 0}} rp </td>
                        <td> {{ $item['rep_results']['month_3'] > 0 ?  number_format($item['rep_results']['month_3'] , 3) : 0}} rp </td>
                        <td> {{ $item['rep_results']['month_4'] > 0 ?  number_format($item['rep_results']['month_4'] , 3) : 0}} rp </td>
                        <td> {{ $item['rep_results']['month_5'] > 0 ?  number_format($item['rep_results']['month_5'] , 3) : 0}} rp </td>
                        <td> {{ $item['rep_results']['month_6'] > 0 ?  number_format($item['rep_results']['month_6'] , 3) : 0}} rp </td>
                        <td> {{ $item['rep_results']['month_7'] > 0 ?  number_format($item['rep_results']['month_7'] , 3) : 0}} rp </td>
                        <td> {{ $item['rep_results']['month_8'] > 0 ?  number_format($item['rep_results']['month_8'] , 3) : 0}} rp </td>
                        <td> {{ $item['rep_results']['month_9'] > 0 ?  number_format($item['rep_results']['month_9'] , 3) : 0}} rp </td>
                        <td> {{ $item['rep_results']['month_10'] > 0 ?  number_format($item['rep_results']['month_10'] , 3)  : 0}} rp </td>
                        <td> {{ $item['rep_results']['month_11'] > 0 ?  number_format($item['rep_results']['month_11'] , 3)  : 0}} rp </td>
                        <td> {{ $item['rep_results']['month_12'] > 0 ?  number_format($item['rep_results']['month_12'] , 3)  : 0}} rp </td>
                        <td> {{ $item['rep_results']['rep_pending'] > 0 ?  number_format($item['rep_results']['rep_pending'] , 3)  : 0}} rp </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endforeach
    </div>
</body>

</html>