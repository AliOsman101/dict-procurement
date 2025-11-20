<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quotation Results – Transparent Evaluation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            color: #333;
            padding: 20px;
        }
        .container {
            background: #fff;
            border-radius: 8px;
            padding: 25px;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #007BFF;
            margin-bottom: 10px;
        }
        h3 {
            margin-top: 25px;
            margin-bottom: 10px;
            color: #333;
        }
        p {
            line-height: 1.6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f1f3f5;
        }
        tr.winner {
            background-color: #d4edda;
        }
        tr.loser {
            background-color: #f8d7da;
        }
        .winner-label {
            color: #155724;
            font-weight: bold;
        }
        .footer {
            margin-top: 25px;
            font-size: 13px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Hello {{ $supplierName }},</h2>
        <p>We appreciate your participation in the quotation process for:</p>
        <p><strong>{{ $procurementTitle }}</strong></p>

        <p><strong>Evaluation Results (All Bidders):</strong></p>

        @foreach($allEvaluationDetails as $eval)
            <h3>
                {{ $eval['supplier_name'] }}
                @if($eval['is_winner'])
                    - <span class="winner-label">Winner</span>
                @endif
            </h3>

            <table>
                <thead>
                    <tr>
                        <th>Item No</th>
                        <th>Description</th>
                        <th>Specifications</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Unit Value</th>
                        <th>Total Value</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($eval['quotes'] as $quote)
                    <tr class="{{ $eval['is_winner'] ? 'winner' : 'loser' }}">
                        <td>{{ $quote['item_no'] }}</td>
                        <td>{{ $quote['description'] }}</td>
                        <td>{{ $quote['specifications'] }}</td>
                        <td>{{ $quote['quantity'] }}</td>
                        <td>{{ $quote['unit'] }}</td>
                        <td>{{ number_format($quote['unit_value'], 2) }}</td>
                        <td>{{ number_format($quote['total_value'], 2) }}</td>
                        <td>{{ $quote['remarks'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach

        <p>Thank you for participating. We encourage you to join our future procurement opportunities.</p>

        <p class="footer">
            This is an automated message from the DICT CAR Procurement System.<br>
            Please do not reply directly to this email.
        </p>
    </div>
</body>
</html>
