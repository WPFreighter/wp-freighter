new Vue({
    el: '#app',
    vuetify: new Vuetify({
        theme: {
            themes: {
                light: {
                    primary: '#0073aa',
                    secondary: '#424242',
                    accent: '#82B1FF',
                    error: '#FF5252',
                    info: '#2196F3',
                    success: '#4CAF50',
                    warning: '#FFC107'
                }
			},
		},
	}),
    data: {
        configurations: wpFreighterSettings.configurations || {},
        stacked_sites: wpFreighterSettings.stacked_sites || [],
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
          { text: '', value: 'stacked_site_id' },
          { text: 'ID', value: 'id' },
          { text: 'Label', value: 'name' },
          { text: 'Domain', value: 'domain' },
          { text: 'Created At', value: 'created_at' },
          { text: '', value: 'actions', align: "right" }
        ],
    },
    methods: {
        pretty_timestamp( date ) {
            d = new Date(0);
            d.setUTCSeconds(date);
            formatted_date = d.toLocaleTimeString( "en-us", {
                "weekday": "short",
                "year": "numeric",
                "month": "short",
                "day": "numeric",
                "hour": "2-digit",
                "minute": "2-digit"
            });
            return formatted_date;
        },
        pretty_timestamp_mysql( date ) {
            d = new Date( date );
            formatted_date = d.toLocaleDateString( "en-us", {
                "weekday": "short",
                "year": "numeric",
                "month": "short",
                "day": "numeric",
            });
            return formatted_date;
        },
        generatePassword() {
            return Math.random().toString(36).slice(-10);
        },
        getNewSiteDefaults() {
            return { 
                name: "", 
                domain: "", 
                title: "", 
                email: wpFreighterSettings.currentUser.email, // Prefill Email
                username: wpFreighterSettings.currentUser.username, // Prefill Username
                password: this.generatePassword(), // Prefill Password
                show: false, 
                valid: true 
            };
        },
        changeForm() {
            this.pending_changes = true
        },
        cloneSite( stacked_site_id ) {
            proceed = confirm( `Clone site ${stacked_site_id} to a new stacked website?` )
            if ( ! proceed ) {
                return
            }
            this.loading = true
            axios.post( wpFreighterSettings.root + 'sites/clone', {
                'source_id': stacked_site_id
            })
            .then( response => {
                this.stacked_sites = response.data
                this.loading = false
            })
            .catch( error => {
                this.loading = false
                console.log( error )
            });
        },
        openCloneDialog( item ) {
            this.clone_site.source_id   = item.stacked_site_id;
            this.clone_site.source_name = item.name ? item.name : 'Site ' + item.stacked_site_id;
            
            // Pre-fill logical defaults
            if ( this.configurations.domain_mapping == 'off' ) {
                this.clone_site.name = item.name ? item.name + " (Clone)" : "";
                this.clone_site.domain = "";
            } else {
                this.clone_site.name = "";
                this.clone_site.domain = ""; // Keep empty for user to input
            }
            
            this.clone_site.show = true;
        },
        openCloneMainDialog() {
            // Explicitly set source_id to 'main' to trigger the new backend logic
            this.clone_site.source_id   = 'main';
            this.clone_site.source_name = "Main Site";
            
            // Reset fields
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
                // Reset clone data
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
            
            // Fetch stats first
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
                // Fallback to simple confirm if stats fail
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
                // If we just deleted the site we are on, reload the page to exit
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
                        // Update local data
                        this.configurations = response.data;
                        this.pending_changes = false;
                        
                        // Trigger Snackbar
                        this.snackbarText = "Configurations saved.";
                        this.snackbar = true;
                    })
                    .catch( error => {
                        console.log( error )
                    });
        },
        switchTo( stacked_site_id ) {
            axios.post( wpFreighterSettings.root + 'switch', {
                'site_id': stacked_site_id,
            } )
            .then( response => {
                // If a login URL is returned, redirect to it to re-authenticate
                if ( response.data.url ) {
                    window.location.href = response.data.url;
                } else {
                    // Fallback
                    location.reload();
                }
            })
            .catch( error => {
                console.log( error )
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
                this.snackbarText = "Autologin failed: " + (error.response.data.message || error.message);
                this.snackbar = true;
                console.log( error );
            });
        },
        newSite() {
            this.$refs.form.validate()
            if ( ! this.new_site.valid ) {
                return
            }
            proceed = confirm( "Create a new stacked website?" )
            if ( ! proceed ) {
                return
            }
            this.loading = true
            this.new_site.show = false
            
            axios.post( wpFreighterSettings.root + 'sites', this.new_site )
                .then( response => {
                        this.stacked_sites = response.data
                        this.loading = false
                        this.new_site = this.getNewSiteDefaults();
                })
                .catch( error => {
                    this.loading = false
                    console.log( error )
                });
        },
        cloneExisting() {
            proceed = confirm( "Clone existing site to a new stacked website?" )
            if ( ! proceed ) {
                return
            }
            axios.post( wpFreighterSettings.root + 'sites/clone' )
                .then( response => {
                        this.stacked_sites = response.data
                    })
                    .catch( error => {
                        console.log( error )
                    });
        }
    }
})