<script src="https://cdn.jsdelivr.net/npm/axios@1.13.2/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vue@2.7.16/dist/vue.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vuetify@2.6.13/dist/vuetify.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/vuetify@2.6.13/dist/vuetify.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@mdi/font/css/materialdesignicons.min.css" rel="stylesheet">

<script>
    // Define WP API Settings locally
    const wpFreighterSettings = {
        root: "<?php echo esc_url_raw( rest_url( 'wp-freighter/v1/' ) ); ?>",
        nonce: "<?php echo wp_create_nonce( 'wp_rest' ); ?>",
        currentUser: {
            username: "<?php echo esc_js( wp_get_current_user()->user_login ); ?>",
            email: "<?php echo esc_js( wp_get_current_user()->user_email ); ?>"
        }
    };

    // Configure Axios to use the nonce and JSON headers
    axios.defaults.headers.common['X-WP-Nonce'] = wpFreighterSettings.nonce;
    axios.defaults.headers.common['Content-Type'] = 'application/json';
</script>

<style>
[v-cloak] > * {
    display:none;
}
[v-cloak]::before {
    display: block;
    position: relative;
    left: 0%;
    top: 0%;
    max-width: 1000px;
    margin:auto;
    padding-bottom: 10em;
}
body #app {
    line-height: initial;
}
.theme--light.v-data-table > .v-data-table__wrapper > table > tbody > tr:hover:not(.v-data-table__expanded__content):not(.v-data-table__empty-wrapper) {
    background: none;
}
input[type=checkbox], input[type=color], input[type=date], input[type=datetime-local], input[type=datetime], input[type=email], input[type=month], input[type=number], input[type=password], input[type=radio], input[type=search], input[type=tel], input[type=text], input[type=time], input[type=url], input[type=week], select, textarea {
    border:0px;
    box-shadow: none;
}
input[type=text]:focus {
    box-shadow: none;
}
</style>

<div id="app" v-cloak>
    <v-app style="background:transparent;">
      <v-main>
      <v-layout>
        <v-row>
        <v-col x12 class="mr-4 mt-4">
            <v-card>
            <v-overlay absolute :value="loading" class="align-start">
                <div style="height: 100px;"></div>
                <v-progress-circular size="128" color="white" indeterminate class="mt-16"></v-progress-circular>
            </v-overlay>
            <v-toolbar flat>
                <v-toolbar-title>WP Freighter</v-toolbar-title>
                <v-spacer></v-spacer>
                <v-toolbar-items>
                    <v-btn small text @click="openCloneMainDialog()"><v-icon>mdi-content-copy</v-icon> Clone main site</v-btn>
                    <v-dialog v-model="new_site.show" persistent max-width="600px">
                    <template v-slot:activator="{ on, attrs }">
                        <v-btn small text v-on="on"><v-icon>mdi-plus</v-icon> Add new empty site</v-btn>
                    </template>
                    <v-card>
                        <v-card-title>New Site</v-card-title>
                        <v-card-text>
                        <v-form ref="form" v-model="new_site.valid">
                        <v-container>
                            <v-row>
                            <v-col cols="12" sm="6" md="6" v-show="configurations.domain_mapping == 'off'">
                                <v-text-field v-model="new_site.name" label="Label"></v-text-field>
                            </v-col>
                            <v-col cols="12" sm="6" md="6" v-show="configurations.domain_mapping == 'on'">
                                <v-text-field v-model="new_site.domain" label="Domain"></v-text-field>
                            </v-col>
                            <v-col cols="12" sm="6" md="6">
                                <v-text-field v-model="new_site.title" label="Title*" :rules="[ value => !!value || 'Required.' ]"></v-text-field>
                            </v-col>
                            <v-col cols="12" sm="6" md="6">
                                <v-text-field v-model="new_site.email" label="Email*" :rules="[ value => !!value || 'Required.' ]"></v-text-field>
                            </v-col>
                            <v-col cols="12" sm="6" md="6">
                                <v-text-field v-model="new_site.username" label="Username*" :rules="[ value => !!value || 'Required.' ]"></v-text-field>
                            </v-col>
                            <v-col cols="12" sm="6" md="6">
                                <v-text-field 
                                    v-model="new_site.password" 
                                    label="Password*" 
                                    type="text" 
                                    append-icon="mdi-refresh"
                                    @click:append="new_site.password = generatePassword()"
                                    :rules="[ value => !!value || 'Required.' ]"
                                ></v-text-field>
                            </v-col>
                            </v-row>
                        </v-container>
                        <small>*indicates required field</small>
                        </v-form>
                        </v-card-text>
                        <v-card-actions>
                            <v-spacer></v-spacer>
                            <v-btn color="primary" text @click="new_site.show = false">Close</v-btn>
                            <v-btn color="primary" text @click="newSite()">Create new site</v-btn>
                        </v-card-actions>
                    </v-card>
                    </v-dialog>
                </v-toolbar-items>
            </v-toolbar>
            <v-card-text>
                <v-alert type="error" outlined v-if="configurations.unable_to_save">
                    Unable to save WP Freighter configurations to <code>wp-config.php</code>. The following will need to be manually added to your <code>wp-config.php</code> then verified. This should be placed directly after the line <code>$table_prefix = 'wp_';</code>.
                    <v-row align="center">
                        <v-col class="grow">
                        <v-card class="my-3" outlined>
                            <v-card-text>
                                <div v-for="line in configurations.unable_to_save">{{ line }}</div>
                            </v-card-text>
                        </v-card>
                        </v-col>
                        <v-col class="shrink">
                            <v-btn icon title="Copy configurations"><v-icon>mdi-content-copy</v-icon></v-btn>
                        </v-col>
                    </v-row>
                    <v-btn color="primary">
                        Verify Configurations
                    </v-btn>
                </v-alert>
                <v-subheader>Stacked Sites</v-subheader>
                <v-data-table
                    :headers="headers"
                    :items="stacked_sites"
                    :items-per-page="-1"
                    hide-default-header
                    hide-default-footer
                    flat
                    no-data-text="You have no stacked sites."
                >
                <template v-slot:header="{ props: { headers } }">
                    <thead>
                    <tr>
                        <th></th>
                        <th v-show="configurations.domain_mapping == 'off'">Label</th>
                        <th v-show="configurations.domain_mapping == 'on'">Domain Mapping</th>
                        <th v-show="configurations.files == 'dedicated'">Files</th>
                        <th>Created At</th>
                        <th></th>
                    </tr>
                    </thead>
                </template>
                <template v-slot:body="{ items }">
                    <tbody>
                    <tr v-for="item in items">
                        <td width="130px">
                            <v-btn v-if="configurations.domain_mapping == 'off'" small color="primary" @click="switchTo( item.stacked_site_id )">Switch To</v-btn>
                            <v-btn v-else color="primary" :href="`//${item.domain}`" small target="_new"><v-icon small>mdi-open-in-new</v-icon> Open</v-btn>
                        </td>
                        <td v-show="configurations.domain_mapping == 'off'">
                            <v-text-field v-model="item.name" label="" value="item.name" @input="changeForm()"></v-text-field>
                        </td>
                        <td v-show="configurations.domain_mapping == 'on'">
                            <v-text-field v-model="item.domain" label="" value="item.domain" @input="changeForm()"></v-text-field>
                        </td>
                        <td v-show="configurations.files == 'dedicated'">
                            <code>/content/{{ item.stacked_site_id }}/</code>
                        </td>
                        <td>{{ pretty_timestamp( item.created_at ) }}</td>
                        <td width="108px">
                            <v-btn icon @click="openCloneDialog( item )" title="Clone stacked site"><v-icon>mdi-content-copy</v-icon></v-btn>
                            <v-btn icon @click="deleteSite( item.stacked_site_id )" title="Delete stacked site"><v-icon>mdi-delete</v-icon></v-btn>
                        </td>
                    </tr>
                    </tbody>
                </template>
                </v-data-table>
                <v-subheader id="files">
                    Files
                    <v-btn small icon class="mx-1" href="https://wpfreighter.com/support/" target="_blank" title="View Documentation">
                        <v-icon small color="grey lighten-1">mdi-help-circle</v-icon>
                    </v-btn>
                </v-subheader>
                <v-radio-group v-model="configurations.files" @change="changeForm()" dense class="ml-3 mt-0">
                <v-row>
                <v-col style="max-width:150px">
                    <v-radio value="shared">
                        <div slot="label"><strong>Shared</strong></div>
                    </v-radio>
                </v-col>
                <v-col>
                    Single <code>/wp-content/</code> folder. Any file changes to plugins, themes and uploads will affect all sites.
                </v-col>
                </v-row>
                <v-row>
                <v-col style="max-width:150px">
                    <v-radio value="dedicated">
                        <div slot="label"><strong>Dedicated</strong></div>
                    </v-radio>
                </v-col>
                <v-col>
                    Each site will have it's unique <code>/wp-content/</code> folder stored under <code>/content/(site-id)/</code>.
                </v-col>
                </v-row>
                </v-radio-group>
                <v-subheader id="domain-mapping">
                    Domain Mapping
                    <v-btn small icon class="mx-1" href="https://wpfreighter.com/support/" target="_blank" title="View Documentation">
                        <v-icon small color="grey lighten-1">mdi-help-circle</v-icon>
                    </v-btn>
                </v-subheader>
                <v-radio-group v-model="configurations.domain_mapping" @change="changeForm()" dense class="ml-3 mt-0">
                <v-row>
                <v-col style="max-width:150px">
                    <v-radio value="off">
                        <div slot="label"><strong>Off</strong></div>
                    </v-radio>
                </v-col>
                <v-col>
                    Easy option - Only logged in users can view stacked sites. Each site will share existing URL and SSL. 
                </v-col>
                </v-row>
                <v-row>
                <v-col style="max-width:150px">
                    <v-radio value="on">
                        <div slot="label"><strong>On</strong></div>
                    </v-radio>
                </v-col>
                <v-col>
                    Manual setup - DNS updates, domain mapping and SSL installation need to completed with your host provider.
                </v-col>
                </v-row>
                </v-radio-group>
                <v-btn color="primary" @click="saveConfigurations()">Save Configurations</v-btn> <v-chip class="mx-3" input-value="true" label small v-show="pending_changes ">Unsaved configurations pending</v-chip>
                {{ response }}
            </v-card-text>
            </v-card>
        </v-col>
        </v-row>
      </v-layout>
      <v-dialog v-model="clone_site.show" persistent max-width="600px">
            <v-card>
                <v-card-title>Clone Site</v-card-title>
                <v-card-text>
                    <v-form ref="clone_form" v-model="clone_site.valid">
                        <v-container>
                            <v-row>
                                <v-col cols="12">
                                    <p>You are about to clone <strong>{{ clone_site.source_name }}</strong>.</p>
                                </v-col>
                                <v-col cols="12" v-if="configurations.domain_mapping == 'off'">
                                    <v-text-field 
                                        v-model="clone_site.name" 
                                        label="New Site Label"
                                        hint="Enter a name for the cloned site"
                                        persistent-hint
                                    ></v-text-field>
                                </v-col>
                                <v-col cols="12" v-if="configurations.domain_mapping == 'on'">
                                    <v-text-field 
                                        v-model="clone_site.domain" 
                                        label="New Domain" 
                                        placeholder="example.com"
                                        hint="Enter the domain for the cloned site"
                                        persistent-hint
                                    ></v-text-field>
                                </v-col>
                            </v-row>
                        </v-container>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="grey" text @click="clone_site.show = false">Cancel</v-btn>
                    <v-btn color="primary" text @click="processClone()">Confirm Clone</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="delete_site.show" persistent max-width="500px">
            <v-card>
                <v-card-title class="headline">Delete Site?</v-card-title>
                <v-card-text>
                    <p>Are you sure you want to delete this site? This action cannot be undone.</p>
                    
                    <v-alert v-if="delete_site.has_dedicated_content" type="primary" dense text icon="mdi-folder-alert">
                        <strong>Dedicated Content Folder Detected</strong><br/>
                        The following directory and its contents will be permanently deleted:
                        <div class="mt-2 mb-1"><code style="font-size:11px">{{ delete_site.path }}</code></div>
                        <div>Estimated Storage: <strong>{{ delete_site.size }}</strong></div>
                    </v-alert>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="grey" text @click="delete_site.show = false">Cancel</v-btn>
                    <v-btn color="error" text @click="confirmDelete()">Permanently Delete</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-snackbar v-model="snackbar" :timeout="2000" color="primary" bottom right>
            {{ snackbarText }}
        </v-snackbar>
      </v-main>
    </v-app>
  </div>

<script>
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
        response: "",
        snackbar: false,
        snackbarText: "",
        configurations: <?php echo ( new WPFreighter\Configurations )->get_json(); ?>,
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
        stacked_sites: <?php echo ( new WPFreighter\Sites )->get_json(); ?>,
        headers: [
          { text: '', value: 'stacked_site_id' },
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
                        location.reload()
                    })
                 .catch( error => {
                        console.log( error )
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
</script>