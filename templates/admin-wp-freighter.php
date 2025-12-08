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
            <v-card outlined rounded="0">
            <v-overlay :value="loading" z-index="5">
                <v-progress-circular size="64" color="white" indeterminate></v-progress-circular>
            </v-overlay>
            <v-toolbar flat>
                <v-toolbar-title>WP Freighter</v-toolbar-title>
                <v-spacer></v-spacer>
                <v-toolbar-items>
                    <v-btn v-if="configurations.domain_mapping == 'on' && configurations.current_site_id != ''" small text @click="loginToMain()">
                        <v-icon>mdi-login-variant</v-icon> Login to main site
                    </v-btn>
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
                <v-alert type="error" outlined v-if="configurations.errors && configurations.errors.manual_bootstrap_required">
                    <h3 class="headline mb-2">Permission Error: Unable to create bootstrap file</h3>
                    <p>WP Freighter cannot write to your <code>wp-content</code> directory. You must manually create this file to enable your stacked sites.</p>
                    
                    <p><strong>1. Create a new file:</strong><br>
                    <code>/wp-content/freighter.php</code></p>
                    
                    <p><strong>2. Paste the following code into it:</strong></p>
                    
                    <v-textarea
                        outlined
                        readonly
                        :value="configurations.errors.manual_bootstrap_required"
                        height="300px"
                        style="font-family: monospace; font-size: 12px;"
                    ></v-textarea>
                    
                    <v-row>
                        <v-col>
                            <v-btn color="error" @click="saveConfigurations()">
                                <v-icon left>mdi-refresh</v-icon> I have created the file
                            </v-btn>
                        </v-col>
                        <v-col class="text-right">
                            <v-btn text small @click="copyToClipboard(configurations.errors.manual_bootstrap_required)">Copy to Clipboard</v-btn>
                        </v-col>
                    </v-row>
                </v-alert>

                <v-alert type="warning" outlined v-if="configurations.errors && !configurations.errors.manual_bootstrap_required && configurations.errors.manual_config_required">
                    <h3 class="headline mb-2">Setup Required: Update wp-config.php</h3>
                    <p>WP Freighter cannot write to your <code>wp-config.php</code> file. Please add the following snippet manually.</p>
                    
                    <p>Place this code directly <strong>after</strong> the line: <code>$table_prefix = 'wp_';</code></p>
                    
                    <v-card class="my-3 grey lighten-4" outlined>
                        <v-card-text style="font-family: monospace;">
                            <div v-for="line in configurations.errors.manual_config_required">{{ line }}</div>
                        </v-card-text>
                    </v-card>
                    
                    <v-btn color="warning" @click="saveConfigurations()">
                        <v-icon left>mdi-check</v-icon> I have updated wp-config.php
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
                >
                <template v-slot:header="{ props: { headers } }">
                    <thead>
                    <tr>
                        <th></th>
                        <th>ID</th>
                        <th v-show="configurations.domain_mapping == 'off'">Label</th>
                        <th v-show="configurations.domain_mapping == 'on'">Domain Mapping</th>
                        <th v-show="configurations.files == 'dedicated'">Files</th>
                        <th v-show="configurations.files == 'hybrid'">Uploads</th>
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
                        <td width="58px">
                            <code>{{ item.stacked_site_id }}</code>
                        </td>
                        <td v-show="configurations.domain_mapping == 'off'">
                            <v-text-field v-model="item.name" label="" value="item.name" @input="changeForm()"></v-text-field>
                        </td>
                        <td v-show="configurations.domain_mapping == 'on'">
                            <v-text-field v-model="item.domain" label="" value="item.domain" @input="changeForm()"></v-text-field>
                        </td>
                        <td width="160px" v-show="configurations.files == 'dedicated'">
                            <code>/content/{{ item.stacked_site_id }}/</code>
                        </td>
                        <td width="200px" v-show="configurations.files == 'hybrid'">
                            <code>/content/{{ item.stacked_site_id }}/uploads/</code>
                        </td>
                        <td width="220px">{{ pretty_timestamp( item.created_at ) }}</td>
                        <td width="150px" class="text-right">
                            <v-tooltip bottom v-if="configurations.domain_mapping == 'on'">
                                <template v-slot:activator="{ on, attrs }">
                                    <v-btn 
                                        icon 
                                        @click="autoLogin( item )" 
                                        v-bind="attrs" 
                                        v-on="on"
                                    >
                                        <v-icon>mdi-login-variant</v-icon>
                                    </v-btn>
                                </template>
                                <span>Magic Autologin</span>
                            </v-tooltip>

                            <v-tooltip bottom>
                                <template v-slot:activator="{ on, attrs }">
                                    <v-btn 
                                        icon 
                                        @click="openCloneDialog( item )" 
                                        v-bind="attrs" 
                                        v-on="on"
                                    >
                                        <v-icon>mdi-content-copy</v-icon>
                                    </v-btn>
                                </template>
                                <span>Clone site</span>
                            </v-tooltip>

                            <v-tooltip bottom>
                                <template v-slot:activator="{ on, attrs }">
                                    <v-btn 
                                        icon 
                                        @click="deleteSite( item.stacked_site_id )" 
                                        v-bind="attrs" 
                                        v-on="on"
                                    >
                                        <v-icon>mdi-delete</v-icon>
                                    </v-btn>
                                </template>
                                <span>Delete site</span>
                            </v-tooltip>
                        </td>
                    </tr>
                    <tr v-if="items.length === 0">
                        <td colspan="6" class="text-center grey--text pa-4">
                            You have no stacked sites.
                        </td>
                    </tr>
                    </tbody>
                </template>
                </v-data-table>
                <v-subheader id="files">Files</v-subheader>
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
                    <v-radio value="hybrid">
                        <div slot="label"><strong>Hybrid</strong></div>
                    </v-radio>
                </v-col>
                <v-col>
                    Shared <code>plugins</code> and <code>themes</code>, but unique <code>uploads</code> folder stored under <code>/content/(site-id)/uploads/</code>.
                </v-col>
                </v-row>
                <v-row>
                <v-col style="max-width:150px">
                    <v-radio value="dedicated">
                        <div slot="label"><strong>Dedicated</strong></div>
                    </v-radio>
                </v-col>
                <v-col>
                    Each site will have its unique <code>/wp-content/</code> folder stored under <code>/content/(site-id)/</code>.
                </v-col>
                </v-row>
                </v-radio-group>
                <v-subheader id="domain-mapping">Domain Mapping</v-subheader>
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
                    
                    <v-alert v-if="delete_site.has_dedicated_content" color="primary" dense text icon="mdi-folder-alert">
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