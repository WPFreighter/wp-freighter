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

/* --- VISUAL RESTORATION OVERRIDES --- */

/* Force Vuetify app wrapper to be transparent so WP Admin background shows */
.v-application {
    background: transparent !important;
}

/* Tighten up table inputs to match V2 look */
.v-data-table .v-field__input, .v-input input, .v-input input:focus, .v-field__append-inner, .v-field.v-field--variant-underlined .v-field__append-inner {
    padding: 0px;
}
.v-data-table .v-input__details {
    display: none !important;
}

/* Restore button icon sizing */
.v-data-table .v-btn--icon.v-btn--density-default {
    width: 28px;
    height: 28px;
}
.v-text-field input, .v-text-field input:focus {
    border: 0px;
    box-shadow: none;
}
.v-field--variant-plain .v-label.v-field-label, .v-field--variant-underlined .v-label.v-field-label {
    top: 4px;
}
</style>

<div id="app" v-cloak>
    <v-app class="bg-transparent">
      <v-main>
      <v-container fluid class="pa-0">
        <v-row>
        <v-col cols="12" class="mt-6 pr-9 pl-3">
            <v-card rounded="0" class="bg-white">
            
            <v-overlay v-model="loading" class="align-center justify-center" z-index="5">
                <v-progress-circular size="64" color="white" indeterminate></v-progress-circular>
            </v-overlay>

            <v-toolbar flat density="compact" color="white">
                <v-toolbar-title class="font-weight-bold">WP Freighter</v-toolbar-title>
                <v-spacer></v-spacer>
                <v-toolbar-items>
                    <v-btn v-if="configurations.domain_mapping == 'on' && configurations.current_site_id != ''" variant="text" @click="loginToMain()">
                       <v-icon start>mdi-login-variant</v-icon> Login to main site
                    </v-btn>
                    
                    <v-btn variant="text" @click="openCloneMainDialog()">
                        <v-icon start>mdi-content-copy</v-icon> Clone main site
                    </v-btn>
                    
                    <v-dialog v-model="new_site.show" persistent max-width="600px">
                        <template v-slot:activator="{ props }">
                            <v-btn variant="text" v-bind="props">
                                <v-icon start>mdi-plus</v-icon> Add new empty site
                            </v-btn>
                        </template>
                        <v-card>
                            <v-card-title>New Site</v-card-title>
                            <v-card-text>
                            <v-form ref="form" v-model="new_site.valid">
                            <v-container>
                                <v-row>
                                <v-col cols="12" sm="6" md="6" v-show="configurations.domain_mapping == 'off'">
                                    <v-text-field v-model="new_site.name" label="Label" variant="underlined"></v-text-field>
                                </v-col>
                                <v-col cols="12" sm="6" md="6" v-show="configurations.domain_mapping == 'on'">
                                    <v-text-field v-model="new_site.domain" label="Domain" variant="underlined"></v-text-field>
                                </v-col>
                                <v-col cols="12" sm="6" md="6">
                                    <v-text-field v-model="new_site.title" label="Title*" :rules="[ value => !!value || 'Required.' ]" variant="underlined"></v-text-field>
                                </v-col>
                                <v-col cols="12" sm="6" md="6">
                                    <v-text-field v-model="new_site.email" label="Email*" :rules="[ value => !!value || 'Required.' ]" variant="underlined"></v-text-field>
                                </v-col>
                                <v-col cols="12" sm="6" md="6">
                                    <v-text-field v-model="new_site.username" label="Username*" :rules="[ value => !!value || 'Required.' ]" variant="underlined"></v-text-field>
                                </v-col>
                                <v-col cols="12" sm="6" md="6">
                                    <v-text-field 
                                        v-model="new_site.password" 
                                        label="Password*" 
                                        type="text" 
                                        append-inner-icon="mdi-refresh"
                                        @click:append-inner="new_site.password = generatePassword()"
                                        hide-details
                                        :rules="[ value => !!value || 'Required.' ]"
                                        variant="underlined"
                                    ></v-text-field>
                                </v-col>
                                </v-row>
                            </v-container>
                            <small class="text-caption">*indicates required field</small>
                            </v-form>
                            </v-card-text>
                            <v-card-actions>
                                <v-spacer></v-spacer>
                                <v-btn color="primary" variant="text" @click="new_site.show = false">Close</v-btn>
                                <v-btn color="primary" variant="text" @click="newSite()">Create new site</v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-dialog>
                </v-toolbar-items>
            </v-toolbar>
    
            <v-card-text>
                <v-alert type="error" variant="outlined" v-if="configurations.errors && configurations.errors.manual_bootstrap_required">
                    <h3 class="text-h6 mb-2">Permission Error: Unable to create bootstrap file</h3>
                    <p>WP Freighter cannot write to your <code>wp-content</code> directory. You must manually create this file to enable your stacked sites.</p>
                    
                    <p class="mt-2"><strong>1. Create a new file:</strong><br>
                    <code>/wp-content/freighter.php</code></p>
                    
                    <p><strong>2. Paste the following code into it:</strong></p>
                    
                    <v-textarea
                        variant="outlined"
                        readonly
                        :model-value="configurations.errors.manual_bootstrap_required"
                        height="300px"
                        class="mt-2"
                        style="font-family: monospace; font-size: 12px;"
                    ></v-textarea>
                    
                    <v-row class="mt-2">
                        <v-col>
                            <v-btn color="error" @click="saveConfigurations()">
                                <v-icon start>mdi-refresh</v-icon> I have created the file
                            </v-btn>
                        </v-col>
                        <v-col class="text-right">
                            <v-btn variant="text" size="small" @click="copyToClipboard(configurations.errors.manual_bootstrap_required)">Copy to Clipboard</v-btn>
                        </v-col>
                    </v-row>
                </v-alert>

                <v-alert type="warning" variant="outlined" v-if="configurations.errors && !configurations.errors.manual_bootstrap_required && configurations.errors.manual_config_required">
                    <h3 class="text-h6 mb-2">Setup Required: Update wp-config.php</h3>
                    <p>WP Freighter cannot write to your <code>wp-config.php</code> file. Please add the following snippet manually.</p>
                    
                    <p class="mt-2">Place this code directly <strong>after</strong> the line: <code>$table_prefix = 'wp_';</code></p>
                    
                    <v-card class="my-3 grey-lighten-4" variant="outlined">
                        <v-card-text style="font-family: monospace;">
                            <div v-for="line in configurations.errors.manual_config_required">{{ line }}</div>
                        </v-card-text>
                    </v-card>
                    
                    <v-btn color="warning" @click="saveConfigurations()">
                        <v-icon start>mdi-check</v-icon> I have updated wp-config.php
                    </v-btn>
                </v-alert>

                <div class="text-subtitle text-medium-emphasis mb-2 ml-2">Stacked Sites</div>

                <v-data-table
                    :headers="headers"
                    :items="stacked_sites"
                    :items-per-page="-1"
                    hide-default-footer
                >
                <template v-slot:headers="{ columns, isSorted, getSortIcon, toggleSort }">
                    <tr>
                        <th></th>
                        <th class="cursor-pointer font-weight-bold" @click="toggleSort(columns.find(c => c.key === 'id'))">
                            ID
                            <v-icon v-if="isSorted(columns.find(c => c.key === 'id'))" :icon="getSortIcon(columns.find(c => c.key === 'id'))"></v-icon>
                        </th>
                        <th v-show="configurations.domain_mapping == 'off'" class="cursor-pointer font-weight-bold" @click="toggleSort(columns.find(c => c.key === 'name'))">
                            Label
                            <v-icon v-if="isSorted(columns.find(c => c.key === 'name'))" :icon="getSortIcon(columns.find(c => c.key === 'name'))"></v-icon>
                        </th>
                        <th v-show="configurations.domain_mapping == 'on'" class="cursor-pointer font-weight-bold" @click="toggleSort(columns.find(c => c.key === 'domain'))">
                            Domain Mapping
                            <v-icon v-if="isSorted(columns.find(c => c.key === 'domain'))" :icon="getSortIcon(columns.find(c => c.key === 'domain'))"></v-icon>
                        </th>
                        <th v-show="configurations.files == 'dedicated'" class="font-weight-bold">
                            Files
                        </th>

                        <th v-show="configurations.files == 'hybrid'" class="font-weight-bold">
                            Uploads
                        </th>
                        <th class="cursor-pointer font-weight-bold" @click="toggleSort(columns.find(c => c.key === 'created_at'))">
                            Created At
                            <v-icon v-if="isSorted(columns.find(c => c.key === 'created_at'))" :icon="getSortIcon(columns.find(c => c.key === 'created_at'))"></v-icon>
                        </th>
                        <th></th>
                    </tr>
                </template>
                <template v-slot:item="{ item }">
                    <tr>
                        <td width="130px" class="pa-2">
                            <v-btn v-if="configurations.domain_mapping == 'off'" size="small" color="primary" class="text-white" elevation="0" @click="switchTo( item.stacked_site_id )">Switch To</v-btn>
                            <v-btn v-else color="primary" :href="`//${item.domain}`" size="small" target="_new" variant="flat" class="text-white">
                                <v-icon size="small" start>mdi-open-in-new</v-icon> Open
                            </v-btn>
                        </td>
                        <td width="58px" class="text-caption">
                            {{ item.stacked_site_id }}
                        </td>
                        <td v-show="configurations.domain_mapping == 'off'">
                            <v-text-field v-model="item.name" density="compact" hide-details variant="underlined" hide-details color="primary" bg-color="white" @input="changeForm()"></v-text-field>
                        </td>
                        <td v-show="configurations.domain_mapping == 'on'">
                            <v-text-field v-model="item.domain" density="compact" hide-details variant="underlined" hide-details color="primary" bg-color="white" @input="changeForm()"></v-text-field>
                        </td>
                        <td width="160px" v-show="configurations.files == 'dedicated'">
                            <code>/content/{{ item.stacked_site_id }}/</code>
                        </td>
                        <td width="200px" v-show="configurations.files == 'hybrid'">
                            <code>/content/{{ item.stacked_site_id }}/uploads/</code>
                        </td>
                        <td width="235px">{{ pretty_timestamp( item.created_at ) }}</td>
                        <td width="150px" class="text-right">
                            
                            <v-tooltip location="bottom" v-if="configurations.domain_mapping == 'on'">
                                <template v-slot:activator="{ props }">
                                    <v-btn icon="mdi-login-variant" variant="text" color="grey-darken-1" @click="autoLogin( item )" v-bind="props"></v-btn>
                                </template>
                                <span>Magic Autologin</span>
                            </v-tooltip>

                            <v-tooltip location="bottom">
                                <template v-slot:activator="{ props }">
                                    <v-btn icon="mdi-content-copy" variant="text" color="grey-darken-1" @click="openCloneDialog( item )" v-bind="props"></v-btn>
                                </template>
                                <span>Clone site</span>
                            </v-tooltip>

                            <v-tooltip location="bottom">
                                <template v-slot:activator="{ props }">
                                    <v-btn icon="mdi-delete" variant="text" color="grey-darken-1" @click="deleteSite( item.stacked_site_id )" v-bind="props"></v-btn>
                                </template>
                                <span>Delete site</span>
                            </v-tooltip>
                        </td>
                    </tr>
                </template>
                <template v-slot:no-data>
                    <div class="text-center grey--text pa-4">
                        You have no stacked sites.
                    </div>
                </template>
                </v-data-table>

                <div class="text-subtitle-2 text-medium-emphasis mt-6 mb-2" id="files">Files</div>
                <v-radio-group v-model="configurations.files" @change="changeForm()" density="compact" class="ml-3 mt-0">
                    <v-row no-gutters class="mb-2">
                        <v-col style="max-width:150px">
                            <v-radio value="shared" color="primary">
                                <template v-slot:label><strong class="text-body-2 text-high-emphasis">Shared</strong></template>
                            </v-radio>
                        </v-col>
                        <v-col class="d-flex align-center text-body-2">
                            Single <code class="mx-1">/wp-content/</code> folder. Any file changes to plugins, themes and uploads will affect all sites.
                        </v-col>
                    </v-row>
                    <v-row no-gutters class="mb-2">
                        <v-col style="max-width:150px">
                            <v-radio value="hybrid" color="primary">
                                <template v-slot:label><strong class="text-body-2 text-high-emphasis">Hybrid</strong></template>
                            </v-radio>
                        </v-col>
                        <v-col class="d-flex align-center text-body-2">
                            Shared <code class="mx-1">plugins</code> and <code class="mx-1">themes</code>, but unique <code class="mx-1">uploads</code> folder stored under <code class="mx-1">/content/(site-id)/uploads/</code>.
                        </v-col>
                    </v-row>
                    <v-row no-gutters>
                        <v-col style="max-width:150px">
                            <v-radio value="dedicated" color="primary">
                                <template v-slot:label><strong class="text-body-2 text-high-emphasis">Dedicated</strong></template>
                            </v-radio>
                        </v-col>
                        <v-col class="d-flex align-center text-body-2">
                            Each site will have its unique <code class="mx-1">/wp-content/</code> folder stored under <code class="mx-1">/content/(site-id)/</code>.
                        </v-col>
                    </v-row>
                </v-radio-group>

                <div class="text-subtitle-2 text-medium-emphasis mt-4 mb-2" id="domain-mapping">Domain Mapping</div>
                <v-radio-group v-model="configurations.domain_mapping" @change="changeForm()" density="compact" class="ml-3 mt-0">
                    <v-row no-gutters class="mb-2">
                        <v-col style="max-width:150px">
                            <v-radio value="off" color="primary">
                                <template v-slot:label><strong class="text-body-2 text-high-emphasis">Off</strong></template>
                            </v-radio>
                        </v-col>
                        <v-col class="d-flex align-center text-body-2">
                            Easy option - Only logged in users can view stacked sites. Each site will share existing URL and SSL.
                        </v-col>
                    </v-row>
                    <v-row no-gutters>
                        <v-col style="max-width:150px">
                            <v-radio value="on" color="primary">
                                <template v-slot:label><strong class="text-body-2 text-high-emphasis">On</strong></template>
                            </v-radio>
                        </v-col>
                        <v-col class="d-flex align-center text-body-2">
                            Manual setup - DNS updates, domain mapping and SSL installation need to completed with your host provider.
                        </v-col>
                    </v-row>
                </v-radio-group>

                <div class="mt-6">
                    <v-btn color="primary" class="text-white" elevation="0" @click="saveConfigurations()">Save Configurations</v-btn> 
                    <v-chip class="mx-3" v-if="pending_changes" color="warning" label size="small">Unsaved configurations pending</v-chip>
                    {{ response }}
                </div>
            </v-card-text>
            </v-card>
        </v-col>
        </v-row>
      </v-container>

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
                                        variant="underlined"
                                        persistent-hint
                                    ></v-text-field>
                                </v-col>
                                <v-col cols="12" v-if="configurations.domain_mapping == 'on'">
                                    <v-text-field 
                                        v-model="clone_site.domain" 
                                        label="New Domain" 
                                        placeholder="example.com"
                                        hint="Enter the domain for the cloned site"
                                        variant="underlined"
                                        persistent-hint
                                    ></v-text-field>
                                </v-col>
                            </v-row>
                        </v-container>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="grey" variant="text" @click="clone_site.show = false">Cancel</v-btn>
                    <v-btn color="primary" variant="text" @click="processClone()">Confirm Clone</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="delete_site.show" persistent max-width="500px">
            <v-card>
                <v-card-title class="text-h5">Delete Site?</v-card-title>
                <v-card-text>
                    <p>Are you sure you want to delete this site? This action cannot be undone.</p>
                    
                    <v-alert v-if="delete_site.has_dedicated_content" color="primary" density="compact" variant="text" icon="mdi-folder-alert" class="mt-3">
                        <strong>Dedicated Content Folder Detected</strong><br/>
                        The following directory and its contents will be permanently deleted:
                        <div class="mt-2 mb-1"><code style="font-size:11px">{{ delete_site.path }}</code></div>
                        <div>Estimated Storage: <strong>{{ delete_site.size }}</strong></div>
                    </v-alert>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="grey" variant="text" @click="delete_site.show = false">Cancel</v-btn>
                    <v-btn color="error" variant="text" @click="confirmDelete()">Permanently Delete</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-snackbar v-model="snackbar" :timeout="2000" color="primary" location="bottom right">
            {{ snackbarText }}
        </v-snackbar>
      </v-main>
    </v-app>
  </div>