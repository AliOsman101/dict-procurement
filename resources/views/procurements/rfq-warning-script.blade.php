<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('rfq-warning', (data) => {
        Swal.fire({
            icon: 'warning',
            title: 'Restriction',
            text: data.message,
            confirmButtonColor: '#3085d6',
        });
    });
});
</script>
