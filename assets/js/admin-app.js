const { createApp } = Vue;
const { createVuetify } = Vuetify;
const vuetify = createVuetify({
    theme: {
        defaultTheme: 'light',
        themes: {
            light: {
                colors: {
                    primary: '#0073aa',
                    secondary: '#424242',
                    accent: '#82B1FF',
                    error: '#FF5252',
                    info: '#2196F3',
                    success: '#4CAF50',
                    warning: '#FFC107',
                    background: '#FFFFFF',
                    surface: '#FFFFFF',
                }
            },
            dark: {
                dark: true,
                colors: {
                    primary: '#72aee6', // WP Admin Dark Mode Blue
                    secondary: '#424242',
                    surface: '#1e1e1e',
                    background: '#121212',
                    error: '#CF6679',
                }
            }
        },
    },
});

createApp({
    data() {
        return {
            configurations: wpFreighterSettings.configurations || {},
            stacked_sites: wpFreighterSettings.stacked_sites || [],
            current_site_id: wpFreighterSettings.current_site_id || "",
            response: "",
            snackbar: false,
            snackbarText: "",
            new_site: { 
                name: "", 
                domain: "", 
                title: "", 
                email: wpFreighterSettings.currentUser.email,
                username: wpFreighterSettings.currentUser.username,
                password: Math.random().toString(36).slice(-10),
                show: false, 
                valid: true 
            },
            clone_site: {
                show: false,
                valid: true,
                source_id: null,
                source_name: "",
                name: "",
                domain: ""
            },
            delete_site: {
                show: false,
                id: null,
                has_dedicated_content: false,
                path: "",
                size: ""
            },
            pending_changes: false,
            loading: false,
            headers: [
                { title: '', key: 'stacked_site_id', sortable: false },
                { title: 'ID', key: 'id' },
                { title: 'Label', key: 'name' },
                { title: 'Domain', key: 'domain' },
                { title: 'Created At', key: 'created_at', headerProps: { class: 'd-none d-md-table-cell' } },
                { title: '', key: 'actions', align: "end", sortable: false }
            ],
        };
    },
    mounted() {
        // Load Dark Mode preference
        const savedTheme = localStorage.getItem('wpFreighterTheme');
        if (savedTheme) {
            this.$vuetify.theme.global.name = savedTheme;
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            this.$vuetify.theme.global.name = 'dark';
        }
        
        // Apply background immediately
        this.updateBodyBackground();
    },
    watch: {
        // Watch for theme changes to update WordPress admin background
        '$vuetify.theme.global.name'() {
            this.updateBodyBackground();
        }
    },
    methods: {
        toggleTheme() {
            const current = this.$vuetify.theme.global.name;
            const next = current === 'light' ? 'dark' : 'light';
            this.$vuetify.theme.global.name = next;
            localStorage.setItem('wpFreighterTheme', next);
        },
        updateBodyBackground() {
            const isDark = this.$vuetify.theme.global.name === 'dark';
            const wpContent = document.querySelector('#wpcontent');
            if (wpContent) {
                // #121212 matches the Vuetify Dark theme background defined above
                wpContent.style.backgroundColor = isDark ? '#121212' : '';
            }
        },
        pretty_timestamp( date ) {
            let d = new Date(0);
            d.setUTCSeconds(date);
            return d.toLocaleTimeString( "en-us", {
                "weekday": "short",
                "year": "numeric",
                "month": "short",
                "day": "numeric",
                "hour": "2-digit",
                "minute": "2-digit"
            });
        },
        pretty_timestamp_mysql( date ) {
            let d = new Date( date );
            return d.toLocaleDateString( "en-us", {
                "weekday": "short",
                "year": "numeric",
                "month": "short",
                "day": "numeric",
            });
        },
        copyToClipboard( text ) {
            if ( !navigator.clipboard ) {
                this.snackbarText = "Clipboard API not supported via non-secure context.";
                this.snackbar = true;
                return;
            }

            navigator.clipboard.writeText( text ).then( () => {
                this.snackbarText = "Code copied to clipboard.";
                this.snackbar = true;
            }, ( err ) => {
                this.snackbarText = "Failed to copy: " + err;
                this.snackbar = true;
            });
        },
        generatePassword() {
            return Math.random().toString(36).slice(-10);
        },
        getNewSiteDefaults() {
            return { 
                name: "", 
                domain: "", 
                title: "", 
                email: wpFreighterSettings.currentUser.email,
                username: wpFreighterSettings.currentUser.username,
                password: this.generatePassword(),
                show: false, 
                valid: true 
            };
        },
        changeForm() {
            this.pending_changes = true;
        },
        cloneSite( stacked_site_id ) {
            let proceed = confirm( `Clone site ${stacked_site_id} to a new tenant website?` );
            if ( ! proceed ) {
                return;
            }
            this.loading = true;
            axios.post( wpFreighterSettings.root + 'sites/clone', {
                'source_id': stacked_site_id
            })
            .then( response => {
                this.stacked_sites = response.data;
                this.loading = false;
            })
            .catch( error => {
                this.loading = false;
                console.log( error );
            });
        },
        openCloneDialog( item ) {
            this.clone_site.source_id   = item.stacked_site_id;
            if ( this.configurations.domain_mapping == 'on' ) {
                this.clone_site.source_name = item.domain ? item.domain : 'Site ' + item.stacked_site_id;
            } else {
                this.clone_site.source_name = item.name ? item.name : 'Site ' + item.stacked_site_id;
            }

            if ( this.configurations.domain_mapping == 'off' ) {
                this.clone_site.name = item.name ? item.name + " (Clone)" : "";
                this.clone_site.domain = "";
            } else {
                this.clone_site.name = "";
                this.clone_site.domain = ""; 
            }
            this.clone_site.show = true;
        },
        openCloneMainDialog() {
            this.clone_site.source_id   = 'main';
            this.clone_site.source_name = "Main Site";
            this.clone_site.name = "";
            this.clone_site.domain = "";
            this.clone_site.show = true;
        },
        processClone() {
            this.loading = true;
            this.clone_site.show = false;

            axios.post( wpFreighterSettings.root + 'sites/clone', {
                'source_id': this.clone_site.source_id,
                'name':      this.clone_site.name,
                'domain':    this.clone_site.domain
            })
            .then( response => {
                this.stacked_sites = response.data;
                this.loading = false;
                this.clone_site.source_id = null;
                this.clone_site.name = "";
                this.clone_site.domain = "";
            })
            .catch( error => {
                this.loading = false;
                console.log( error );
            });
        },
        deleteSite( stacked_site_id ) {
            this.loading = true;
            this.delete_site.id = stacked_site_id;
            
            axios.post( wpFreighterSettings.root + 'sites/stats', {
                'site_id': stacked_site_id
            })
            .then( response => {
                this.delete_site.has_dedicated_content = response.data.has_dedicated_content;
                this.delete_site.path = response.data.path;
                this.delete_site.size = response.data.size;
                
                this.loading = false;
                this.delete_site.show = true;
            })
            .catch( error => {
                this.loading = false;
                console.log( error );
                if ( confirm( `Delete site ${stacked_site_id}?` ) ) {
                    this.confirmDelete();
                }
            });
        },
        confirmDelete() {
            this.delete_site.show = false;
            this.loading = true;
            
            axios.post( wpFreighterSettings.root + 'sites/delete', {
                'site_id': this.delete_site.id,
            } )
            .then( response => {
                if ( this.delete_site.id == wpFreighterSettings.current_site_id ) {
                    location.reload();
                    return;
                }
                this.stacked_sites = response.data;
                this.loading = false;
                this.snackbarText = "Site deleted successfully.";
                this.snackbar = true;
            })
            .catch( error => {
                this.loading = false;
                console.log( error );
            });
        },
        saveConfigurations() {
            axios.post( wpFreighterSettings.root + 'configurations', {
                sites: this.stacked_sites,
                configurations: this.configurations,
            } )
            .then( response => {
                this.configurations = response.data;
                this.pending_changes = false;
                this.snackbarText = "Configurations saved.";
                this.snackbar = true;
            })
            .catch( error => {
                console.log( error );
            });
        },
        switchTo( stacked_site_id ) {
            axios.post( wpFreighterSettings.root + 'switch', {
                'site_id': stacked_site_id,
            } )
            .then( response => {
                if ( response.data.url ) {
                    window.location.href = response.data.url;
                } else {
                    location.reload();
                }
            })
            .catch( error => {
                console.log( error );
            });
        },
        autoLogin( item ) {
            this.loading = true;
            axios.post( wpFreighterSettings.root + 'sites/autologin', {
                'site_id': item.stacked_site_id
            })
            .then( response => {
                this.loading = false;
                if ( response.data.url ) {
                    window.open( response.data.url, '_blank' );
                }
            })
            .catch( error => {
                this.loading = false;
                this.snackbarText = "Autologin failed: " + (error.response?.data?.message || error.message);
                this.snackbar = true;
                console.log( error );
            });
        },
        loginToMain() {
            this.loading = true;
            axios.post( wpFreighterSettings.root + 'sites/autologin', {
                'site_id': 'main'
            })
            .then( response => {
                this.loading = false;
                if ( response.data.url ) {
                    window.location.href = response.data.url;
                }
            })
            .catch( error => {
                this.loading = false;
                this.snackbarText = "Login failed: " + (error.response?.data?.message || error.message);
                this.snackbar = true;
                console.log( error );
            });
        },
        newSite() {
            this.$refs.form.validate().then(result => {
                if (!result.valid) {
                    return;
                }
                let proceed = confirm( "Create a new tenant website?" );
                if ( ! proceed ) {
                    return;
                }
                this.loading = true;
                this.new_site.show = false;
                
                axios.post( wpFreighterSettings.root + 'sites', this.new_site )
                    .then( response => {
                        this.stacked_sites = response.data;
                        this.loading = false;
                        this.new_site = this.getNewSiteDefaults();
                    })
                    .catch( error => {
                        this.loading = false;
                        console.log( error );
                    });
            });
        },
        cloneExisting() {
            let proceed = confirm( "Clone existing site to a new tenant website?" );
            if ( ! proceed ) {
                return;
            }
            axios.post( wpFreighterSettings.root + 'sites/clone' )
                .then( response => {
                    this.stacked_sites = response.data;
                })
                .catch( error => {
                    console.log( error );
                });
        }
    }
}).use(vuetify).mount('#app');