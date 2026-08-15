

<?php
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Jakarta');
include "../konek/konek.php";
// $link=koneksi_db();
$waktu = date("Y-m-d H:i:s");
$page = $_SERVER['PHP_SELF'];
$sec = "10";
$tgll = date('Y-m-d');

function valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

$startDate = isset($_POST['submit']) ? (string) ($_POST['cari1'] ?? '') : $tgll;
$endDate = isset($_POST['submit']) ? (string) ($_POST['cari2'] ?? '') : $tgll;
$endDate = $endDate === '' ? $startDate : $endDate;
$rows = null;
$dateError = null;

if (!valid_date($startDate) || !valid_date($endDate) || $startDate > $endDate) {
    $dateError = 'Rentang tanggal tidak valid.';
} else {
    $startTime = $startDate . ' 00:00:00';
    $endTime = $endDate . ' 23:59:59';
    $statement = $link->prepare('SELECT * FROM coretb WHERE waktu BETWEEN ? AND ? ORDER BY waktu DESC');
    $statement->bind_param('ss', $startTime, $endTime);
    $statement->execute();
    $rows = $statement->get_result();
}
?>


<html lang="en">

  <head>
  <!--   <meta http-equiv="refresh" content="<?php echo $sec?>;URL='<?php echo $page?>'" charset="utf-8"> -->
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.1/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.0.0/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.10.0/css/all.css" integrity="sha384-AYmEC3Yw5cVb3ZcuHtOA93w35dYTsvhLPVnYs9eStHfGJvOvKxVfELGroGkvsg+p" crossorigin="anonymous"/>

    <title>download data</title>
  </head>
  <body>
    <div class="container">
     <div class="row">
                        <div class="col-md-6 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <div class="form-group">
                                        <form action="" method="post">
                                            <label class="col-md-12">Tanggal Awal</label>
                                            <div class="col-md-12">
                                                <input type="date" class="form-control form-control-line" name="cari1">
                                                <label style="font-size: 10px;"><span>*</span> Kosongkan tanggal akhir apabila hanya akan menampilkan data 1 hari</label>
                                            </div>
                                            <div class="col-md-12">
                                                <button class="btn btn-success" name="submit">Select</button>
                                            </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <div class="form-group">
                                        <label class="col-md-12">Tanggal Akhir</label>
                                        <div class="col-md-12">
                                            <input type="date" class="form-control form-control-line" name="cari2">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        </form>

                    </div>
                    <br>
                    <a href="../dashboard/index.php"><button class="btn btn-primary "> <i class="fas fa-undo"></i>Back</button></a>
                    <br>
    <!-- <h1>Hello, world!</h1> -->
 <div class="row">
    <div class="col-md-12 grid-margin stretch-card">
      <div class="card">
         <div class="card-body">
        <p class="card-title text-md-center text-xl-left">DATA PARTIKULAT 02</p>

    <table id="example" class="display" style="width:100%">
        <thead>
            <tr>
                <th colspan="6" align="center"> Data Gas </th>
            </tr>
            <tr>
                <th> No </th>
               <th>waktu</th>
                <th>pm1</th>
                <th>pm25</th>
                <th>pm10</th>
                <th>temp</th>
                <th>humd</th>
                <th>press</th>

            </tr>
        </thead>
        <tbody>

            <?php
            if ($dateError !== null) {
                echo '<tr><td colspan="8">' . htmlspecialchars($dateError, ENT_QUOTES, 'UTF-8') . '</td></tr>';
            } elseif ($rows !== null && $rows->num_rows > 0) {
                $k = 1;
                while ($data = $rows->fetch_assoc()) {
            ?>

            <tr>
                <td><?php echo $k++; ?></td>
                <td><?php echo htmlspecialchars((string) $data['waktu'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $data['pm1'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $data['pm25'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $data['pm10'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $data['temp'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $data['humd'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) $data['press'], ENT_QUOTES, 'UTF-8'); ?></td>

            </tr>
            <?php
                }
            } else {
                echo '<tr><td colspan="8">Data tidak tersedia pada rentang tersebut.</td></tr>';
            }
            ?>
          </tbody>
        </table>
</div>
</div>
</div>
</div>
</div>

    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
  </body>
</html>

<script type="text/javascript">
  $(document).ready(function() {
    $('#example').DataTable( {
        dom: 'Bfrtip',
        // buttons: [
        //     'copyHtml5',
        //     'excelHtml5',
        //     'csvHtml5',
        //     'pdfHtml5'
        // ]
         buttons: [{
                    extend: 'excel',
                    title: 'LAPORAN PARTIKULAT01'
                },
                {
                    extend: 'pdfHtml5',
                    orientation: 'potrait',
                    pageSize: 'A5',
                    download: 'open',
                    title: 'LAPORAN PARTIKULAT01'
                }
            ]
    } );
} );
</script>

<script src="https://code.jquery.com/jquery-3.5.1.js"> </script>
<script src="https://cdn.datatables.net/1.11.1/js/jquery.dataTables.min.js"> </script>
<script src="https://cdn.datatables.net/buttons/2.0.0/js/dataTables.buttons.min.js"> </script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"> </script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"> </script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js
"> </script>
<script src="https://cdn.datatables.net/buttons/2.0.0/js/buttons.html5.min.js"> </script>
