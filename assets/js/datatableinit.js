$(document).ready(function() {
    $('#ac_serial_numbers_stock_manager').DataTable({
        "columnDefs": [
            {
                "targets": [1], // 🔹 যেখানে `select2` আছে সেই কলামের ইনডেক্স দিন
                "searchable": false // 🔹 এই কলামে সার্চ কাজ করবে না
            }
        ]
    });
} );