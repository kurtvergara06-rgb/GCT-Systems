document.addEventListener('DOMContentLoaded', function () {

  /*
  |--------------------------------------------------------------------------
  | Modal Helpers
  |--------------------------------------------------------------------------
  */

  function openModal(modal) {
    if (modal) {
      modal.classList.add('show');
    }
  }

  function closeModal(modal) {
    if (modal) {
      modal.classList.remove('show');
    }
  }


  /*
  |--------------------------------------------------------------------------
  | Import Driver Attendance Modal
  |--------------------------------------------------------------------------
  */

  const importDriverAttendanceModal =
    document.getElementById(
      'importDriverAttendanceModal'
    );

  const openImportDriverAttendanceModal =
    document.getElementById(
      'openImportDriverAttendanceModal'
    );

  const closeImportDriverAttendanceModal =
    document.getElementById(
      'closeImportDriverAttendanceModal'
    );

  const cancelImportDriverAttendanceModal =
    document.getElementById(
      'cancelImportDriverAttendanceModal'
    );


  if (
    openImportDriverAttendanceModal
  ) {
    openImportDriverAttendanceModal
      .addEventListener(
        'click',
        function () {
          openModal(
            importDriverAttendanceModal
          );
        }
      );
  }


  if (
    closeImportDriverAttendanceModal
  ) {
    closeImportDriverAttendanceModal
      .addEventListener(
        'click',
        function () {
          closeModal(
            importDriverAttendanceModal
          );
        }
      );
  }


  if (
    cancelImportDriverAttendanceModal
  ) {
    cancelImportDriverAttendanceModal
      .addEventListener(
        'click',
        function () {
          closeModal(
            importDriverAttendanceModal
          );
        }
      );
  }


  /*
  |--------------------------------------------------------------------------
  | Add Driver Attendance Modal
  |--------------------------------------------------------------------------
  */

  const driverAttendanceModal =
    document.getElementById(
      'driverAttendanceModal'
    );

  const openDriverAttendanceModal =
    document.getElementById(
      'openDriverAttendanceModal'
    );

  const closeDriverAttendanceModal =
    document.getElementById(
      'closeDriverAttendanceModal'
    );

  const cancelDriverAttendanceModal =
    document.getElementById(
      'cancelDriverAttendanceModal'
    );


  if (
    openDriverAttendanceModal
  ) {
    openDriverAttendanceModal
      .addEventListener(
        'click',
        function () {
          openModal(
            driverAttendanceModal
          );
        }
      );
  }


  if (
    closeDriverAttendanceModal
  ) {
    closeDriverAttendanceModal
      .addEventListener(
        'click',
        function () {
          closeModal(
            driverAttendanceModal
          );
        }
      );
  }


  if (
    cancelDriverAttendanceModal
  ) {
    cancelDriverAttendanceModal
      .addEventListener(
        'click',
        function () {
          closeModal(
            driverAttendanceModal
          );
        }
      );
  }


  /*
  |--------------------------------------------------------------------------
  | Edit Driver Attendance Modal
  |--------------------------------------------------------------------------
  */

  const editDriverAttendanceModal =
    document.getElementById(
      'editDriverAttendanceModal'
    );

  const editDriverAttendanceForm =
    document.getElementById(
      'editDriverAttendanceForm'
    );

  const closeEditDriverAttendanceModal =
    document.getElementById(
      'closeEditDriverAttendanceModal'
    );

  const cancelEditDriverAttendanceModal =
    document.getElementById(
      'cancelEditDriverAttendanceModal'
    );


  const editDriverId =
    document.getElementById(
      'edit_driver_id'
    );

  const editDriverName =
    document.getElementById(
      'edit_driver_name'
    );

  const editShift =
    document.getElementById(
      'edit_shift'
    );

  const editBusAssignment =
    document.getElementById(
      'edit_bus_assignment'
    );

  const editAttendanceDate =
    document.getElementById(
      'edit_attendance_date'
    );

  const editTimeIn =
    document.getElementById(
      'edit_time_in'
    );

  const editTimeOut =
    document.getElementById(
      'edit_time_out'
    );

  const editStatus =
    document.getElementById(
      'edit_status'
    );


  document
    .querySelectorAll(
      '.open-edit-driver-attendance-modal'
    )
    .forEach(function (button) {

      button.addEventListener(
        'click',
        function () {

          if (
            editDriverAttendanceForm
          ) {
            editDriverAttendanceForm.action =
              button.dataset.updateUrl
              || '#';
          }


          if (editDriverId) {
            editDriverId.value =
              button.dataset.driverId
              || '';
          }


          if (editDriverName) {
            editDriverName.value =
              button.dataset.driverName
              || '';
          }


          if (editShift) {
            editShift.value =
              button.dataset.shift
              || 'Morning';
          }


          if (editBusAssignment) {
            editBusAssignment.value =
              button.dataset.busAssignment
              || '';
          }


          if (editAttendanceDate) {
            editAttendanceDate.value =
              button.dataset.attendanceDate
              || '';
          }


          if (editTimeIn) {
            editTimeIn.value =
              button.dataset.timeIn
              || '';
          }


          if (editTimeOut) {
            editTimeOut.value =
              button.dataset.timeOut
              || '';
          }


          if (editStatus) {
            editStatus.value =
              button.dataset.status
              || 'Present';
          }


          openModal(
            editDriverAttendanceModal
          );
        }
      );
    });


  if (
    closeEditDriverAttendanceModal
  ) {
    closeEditDriverAttendanceModal
      .addEventListener(
        'click',
        function () {
          closeModal(
            editDriverAttendanceModal
          );
        }
      );
  }


  if (
    cancelEditDriverAttendanceModal
  ) {
    cancelEditDriverAttendanceModal
      .addEventListener(
        'click',
        function () {
          closeModal(
            editDriverAttendanceModal
          );
        }
      );
  }


  /*
  |--------------------------------------------------------------------------
  | Delete Driver Attendance Modal
  |--------------------------------------------------------------------------
  */

  const deleteDriverAttendanceModal =
    document.getElementById(
      'deleteDriverAttendanceModal'
    );

  const deleteDriverAttendanceName =
    document.getElementById(
      'deleteDriverAttendanceName'
    );

  const cancelDeleteDriverAttendance =
    document.getElementById(
      'cancelDeleteDriverAttendance'
    );

  const confirmDeleteDriverAttendance =
    document.getElementById(
      'confirmDeleteDriverAttendance'
    );


  let selectedDeleteForm = null;


  document
    .querySelectorAll(
      '.open-delete-driver-attendance-modal'
    )
    .forEach(function (button) {

      button.addEventListener(
        'click',
        function (event) {

          event.preventDefault();

          const attendanceId =
            button.dataset.id;


          selectedDeleteForm =
            document.getElementById(
              `deleteDriverAttendanceForm-${attendanceId}`
            );


          if (
            deleteDriverAttendanceName
          ) {
            deleteDriverAttendanceName.textContent =
              button.dataset.driverName
              ||
              button.dataset.driverId
              ||
              'this driver attendance record';
          }


          openModal(
            deleteDriverAttendanceModal
          );
        }
      );
    });


  if (
    cancelDeleteDriverAttendance
  ) {
    cancelDeleteDriverAttendance
      .addEventListener(
        'click',
        function () {

          selectedDeleteForm = null;

          closeModal(
            deleteDriverAttendanceModal
          );
        }
      );
  }


  if (
    confirmDeleteDriverAttendance
  ) {
    confirmDeleteDriverAttendance
      .addEventListener(
        'click',
        function () {

          if (
            selectedDeleteForm
          ) {
            selectedDeleteForm
              .requestSubmit();
          }
        }
      );
  }


  /*
  |--------------------------------------------------------------------------
  | Close Modal When Clicking Outside
  |--------------------------------------------------------------------------
  */

  document
    .querySelectorAll(
      '.modal-overlay, .delete-modal-overlay, .success-modal-overlay'
    )
    .forEach(function (modal) {

      modal.addEventListener(
        'click',
        function (event) {

          if (
            event.target === modal
          ) {
            closeModal(modal);
          }
        }
      );
    });


  /*
  |--------------------------------------------------------------------------
  | Escape Key
  |--------------------------------------------------------------------------
  */

  document.addEventListener(
    'keydown',
    function (event) {

      if (
        event.key === 'Escape'
      ) {
        closeModal(
          importDriverAttendanceModal
        );

        closeModal(
          driverAttendanceModal
        );

        closeModal(
          editDriverAttendanceModal
        );

        closeModal(
          deleteDriverAttendanceModal
        );
      }
    }
  );

});