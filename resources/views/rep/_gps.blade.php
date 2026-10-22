{{-- Fills hidden lat / lng / accuracy inputs from the phone's GPS before a form submits. Usage: x-data="gpsForm" @submit.prevent="submit($el)" --}}
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('gpsForm', () => ({
            locating: false,
            submit(form) {
                if (this.locating) return;
                const done = () => form.submit();
                if (!navigator.geolocation) return done();
                this.locating = true;
                navigator.geolocation.getCurrentPosition((pos) => {
                    form.querySelector('[name=lat]').value = pos.coords.latitude;
                    form.querySelector('[name=lng]').value = pos.coords.longitude;
                    const acc = form.querySelector('[name=accuracy]');
                    if (acc) acc.value = Math.round(pos.coords.accuracy);
                    done();
                }, () => done(), { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
            },
        }));
    });
</script>
