document.addEventListener('DOMContentLoaded', () => {
  window.addEventListener('system-data-updated', (event) => {
    const payload = event.detail;

    const isAttendanceUpdate =
      payload &&
      payload.module === 'Operation' &&
      payload.entity === 'Attendance';

    if (!isAttendanceUpdate) {
      return;
    }

    setTimeout(() => {
      window.location.reload();
    }, 600);
  });
});