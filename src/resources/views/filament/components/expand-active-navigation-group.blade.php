{{--
    Rendered right after Filament's own sidebar script, before Alpine starts: if the group holding
    the current page is collapsed in this browser, open it, as clicking its heading would.
--}}
@if (filled($group))
    <script data-expand-active-navigation-group>
        (() => {
            const label = @js($group);
            let collapsed;

            try {
                collapsed = JSON.parse(localStorage.getItem('collapsedGroups') ?? 'null');
            } catch {
                return;
            }

            if (! Array.isArray(collapsed) || ! collapsed.includes(label)) {
                return;
            }

            try {
                localStorage.setItem('collapsedGroups', JSON.stringify(collapsed.filter((group) => group !== label)));
            } catch {
                return;
            }

            document.querySelectorAll('.fi-sidebar-group').forEach((group) => {
                if (group.dataset.groupLabel !== label) {
                    return;
                }

                group.classList.remove('fi-collapsed');

                const items = group.querySelector('.fi-sidebar-group-items');

                if (items) {
                    items.style.display = '';
                }
            });
        })();
    </script>
@endif
