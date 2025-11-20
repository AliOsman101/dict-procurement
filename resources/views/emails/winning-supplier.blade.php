<!DOCTYPE html>
<html>
<body>
    <h2>Congratulations {{ $supplierName }}!</h2>
    <p>You have been selected as the <strong>winning supplier</strong> for the procurement titled:</p>
    <p><strong>{{ $procurementTitle }}</strong></p>

    <p><strong>Evaluation Details:</strong></p>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>Specification</th>
                <th>Unit Value</th>
                <th>Total Value</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach($evaluationDetails as $detail)
            <tr>
                <td>{{ $detail['specifications'] }}</td>
                <td>{{ number_format($detail['unit_value'], 2) }}</td>
                <td>{{ number_format($detail['total_value'], 2) }}</td>
                <td>{{ $detail['remarks'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <br>
    <p>Our team will contact you soon for the next steps.</p>
    <br>
    <p>Thank you,</p>
    <p><strong>DICT Procurement Team</strong></p>
</body>
</html>
