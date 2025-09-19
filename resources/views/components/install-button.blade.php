<!-- resources/views/components/install-button.blade.php -->
<button
    x-data="pwaInstaller()"
    x-show="canInstall"
    x-cloak
    @click="install"
    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs
           hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600
           dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500"
>
    📲 Install ArcherDB
</button>

<script>
function pwaInstaller() {
    return {
        deferredPrompt: null,
        canInstall: false,

        init() {
            window.addEventListener("beforeinstallprompt", (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                this.canInstall = true;
            });

            window.addEventListener("appinstalled", () => {
                this.deferredPrompt = null;
                this.canInstall = false;
                console.log("ArcherDB installed");
            });
        },

        async install() {
            if (!this.deferredPrompt) return;
            this.deferredPrompt.prompt();
            const { outcome } = await this.deferredPrompt.userChoice;
            console.log(`Install outcome: ${outcome}`);
            this.deferredPrompt = null;
            this.canInstall = false;
        }
    };
}
</script>
