<script src="https://unpkg.com/qs@6.5.2/dist/qs.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vue@2.6.11/dist/vue.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vuetify@2.3.4/dist/vuetify.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/vuetify@2.3.4/dist/vuetify.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@mdi/font@5.x/css/materialdesignicons.min.css" rel="stylesheet">

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
<form action='options.php' method='post'>
<div id="app" v-cloak>
    <v-app style="background:transparent;">
      <v-main>
      <v-layout>
        <v-row>
        <v-col x12 class="mr-4 mt-4">
            <v-card>
            <v-toolbar flat>
                <v-toolbar-title>Stackable Mode</v-toolbar-title>
                <v-spacer></v-spacer>
                <v-toolbar-items>
                    <v-btn small text @click="cloneExisting()"><v-icon>mdi-content-copy</v-icon> Clone current site</v-btn>
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
                                <v-text-field v-model="new_site.password" label="Password*" type="password" :rules="[ value => !!value || 'Required.' ]"></v-text-field>
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
                    Unable to save Stackable configurations to <code>wp-config.php</code>. The following will need to be manually added to your <code>wp-config.php</code> then verified. This should be placed directly after the line <code>$table_prefix = 'wp_';</code>.
                    <v-row align="center">
                        <v-col class="grow">
                        <v-card class="my-3" outlined>
                            <v-card-text>
                                <div v-for="line in configurations.unable_to_save">{{ line }}</div>
                            </v-card-text>
                        </v-card>
                        </v-col>
                        <v-col class="shrink">
                        <v-tooltip top>
                        <template v-slot:activator="{ on, attrs }">
                            <v-btn icon v-bind="attrs" v-on="on"><v-icon>mdi-content-copy</v-icon></v-btn>
                        </template>
                        <span>Copy Configurations</span>
                        </v-tooltip>
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
                    <tr v-for="item in items" :key="item.name">
                        <td width="130px">
                            <v-btn v-if="configurations.domain_mapping == 'off'" small color="primary" @click="switchTo( item.stacked_site_id )">Switch To</v-btn>
                            <v-btn v-else color="primary" :href="`//${item.domain}`" small target="_new"><v-icon small>mdi-open-in-new</v-icon> Open</v-btn>
                        </td>
                        <td v-show="configurations.domain_mapping == 'off'">
                            <v-text-field v-model="item.name" value="item.name" label="" @input="changeForm()"></v-text-field>
                        </td>
                        <td v-show="configurations.domain_mapping == 'on'">
                            <v-text-field v-model="item.domain" label="" value="item.domain" v-show="configurations.domain_mapping == 'on'" @input="changeForm()"></v-text-field>
                        </td>
                        <td v-show="configurations.files == 'dedicated'">
                            <code>/content/{{ item.stacked_site_id }}/</code>
                        </td>
                        <td>{{ pretty_timestamp( item.created_at) }}</td>
                        <td width="68px">
                            <v-tooltip top>
                                <template v-slot:activator="{ on, attrs }">
                                    <v-btn icon @click="deleteSite( item.stacked_site_id )" v-bind="attrs" v-on="on"><v-icon>mdi-delete</v-icon></v-btn>
                                </template>
                                <span>Delete stacked site</span>
                            </v-tooltip>
                        </td>
                    </tr>
                    </tbody>
                </template>
                </v-data-table>
                <v-subheader id="files">
                    Files
                    <v-tooltip top attach="#files" fixed>
                        <template v-slot:activator="{ on, attrs }">
                            <v-btn small icon class="mx-1" href="https://stackablewp.com/docs/files/" target="_blank" v-bind="attrs" v-on="on">
                                <v-icon small color="grey lighten-1">mdi-help-circle</v-icon>
                            </v-btn>
                        </template>
                        <span>View Documentation</span>
                    </v-tooltip>
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
                    <v-tooltip top attach="#domain-mapping" fixed>
                        <template v-slot:activator="{ on, attrs }">
                            <v-btn small icon class="mx-1" href="https://stackablewp.com/docs/domain-mapping/" target="_blank" v-bind="attrs" v-on="on">
                                <v-icon small color="grey lighten-1">mdi-help-circle</v-icon>
                            </v-btn>
                        </template>
                        <span>View Documentation</span>
                    </v-tooltip>
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
      </v-main>
    </v-app>
  </div>
</form>
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
        configurations: <?php echo ( new StackableMode\Configurations )->get_json(); ?>,
        new_site: { title: "", email: "", username: "", password: "", show: false, valid: true },
        pending_changes: false,
        stacked_sites: <?php echo ( new StackableMode\Sites )->get_json(); ?>,
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
			// takes in '1577584719' then returns "Monday, Jun 18, 2018, 7:44 PM"
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
        changeForm() {
            this.pending_changes = true
        },
        deleteSite( stacked_site_id ) {
            proceed = confirm( `Delete site ${stacked_site_id}?` )
            if ( ! proceed ) {
                return
            }
            var data = {
				'action': 'stacked_ajax',
				'command': "deleteSite",
                'value': stacked_site_id,
			}
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
                        this.stacked_sites = response.data
                    })
                    .catch( error => {
                        console.log( error )
                    });
        },
        saveConfigurations() {
            var data = {
				'action': 'stacked_ajax',
				'command': "saveConfigurations",
                'value': { 
                    sites: this.stacked_sites,
                    configurations: this.configurations,
                }
			}
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
                        location.reload()
                    })
                    .catch( error => {
                        console.log( error )
                    });
        },
        switchTo( stacked_site_id ) {
            var data = {
				'action': 'stacked_ajax',
				'command': "switchTo",
                'value': stacked_site_id,
			}
			axios.post( ajaxurl, Qs.stringify( data ) )
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
            var data = {
				'action': 'stacked_ajax',
				'command': "newSite",
                'value': this.new_site
			}
			axios.post( ajaxurl, Qs.stringify( data ) )
				.then( response => {
                        this.stacked_sites = response.data
                        this.new_site = { title: "", email: "", username: "", password: "", show: false }
                    })
                    .catch( error => {
                        console.log( error )
                    });
        },
        cloneExisting() {
            proceed = confirm( "Clone existing site to a new stacked website?" )
            if ( ! proceed ) {
                return
            }
            var data = {
				'action': 'stacked_ajax',
				'command': "cloneExisting",
			}
			axios.post( ajaxurl, Qs.stringify( data ) )
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