<!--
  -  Factory+ / AMRC Connectivity Stack (ACS) Manager component
  -  Copyright 2023 AMRC
  -->

<template>
  <div class="flex bg-white m-3 overflow-auto flex-1 gap-1 h-100vh">
    <SchemaBrowserOverlay show-new :show="schemaBrowserVisible" @close="schemaBrowserVisible=false"
                          :device-schemas="deviceSchemas"
                          :device-schema-versions="deviceSchemaVersions"
                          @schema-selected="selectSchema"></SchemaBrowserOverlay>
    <div v-if="schema" class="w-2/5 flex flex-col gap-3 p-2">
      <div class="flex items-center gap-2">
        <button @click="maybeNew" class="fpl-button-brand h-10">
          <span>New</span>
          <i class="fa-sharp fa-solid ml-2 fa-plus"></i>
        </button>
        <button @click="open" class="fpl-button-brand h-10">
          <span>Open</span>
          <i class="fa-sharp fa-solid ml-2 fa-folder"></i>
        </button>
      </div>
      <input class="font-bold text-brand text-lg bg-gray-100 p-2" v-model="name">
      <List @new="create" @rowSelected="handleMetricSelection" :guides="false" :properties="schema.properties"></List>
      <div class="flex items-center">
        <button @click="copy" class="fpl-button-brand h-10 flex-1 gap-3">
          <span>Copy JSON</span>
          <i class="fa-sharp fa-solid ml-2 fa-copy"></i>
        </button>
        <button @click="download" class="fpl-button-secondary h-10 flex-1">
          <span>Download</span>
          <i class="fa-sharp fa-solid fa-download ml-2"></i>
        </button>
      </div>
    </div>
    <div class="flex-1 bg-gray-50">
      <MetricEditPanel :selectedMetric="selectedMetric" @updateMetric="updateMetric" @updateName="updateName"></MetricEditPanel>
    </div>
  </div>
</template>

<script>
import useVuelidate from '@vuelidate/core'
import download from 'downloadjs'
import SchemaBrowserOverlay from '@/resources/js/components/Schemas/SchemaBrowserOverlay.vue'
import { v4 as uuidv4 } from 'uuid'

export default {
  setup () {
    return { v$: useVuelidate({ $stopPropagation: true }) }
  },

  name: 'SchemaEditorContainer',

  computed: {
    isInvalid () {
      return this.v$.$dirty && this.v$.$invalid === true
    },
  },

  components: {
    SchemaBrowserOverlay,
    'List': () => import(/* webpackPrefetch: true */ '../SchemaEditor/List.vue'),
    'MetricEditPanel': () => import(/* webpackPrefetch: true */ '../SchemaEditor/MetricEditPanel.vue'),
  },

  props: {
    initialData: {},
  },

  watch: {
    name () {
      this.schema.$id = `https://raw.githubusercontent.com/AMRC-FactoryPlus/schemas/main/${this.name}.json`
    },
  },

  mounted () {
    this.initialiseContainerComponent()

    window.events.$on('schema-editor-select-metric', (metric) => {
      this.selectedMetric = metric
    })

    // Load the schema in the query string if it exists
    let urlParams = new URLSearchParams(window.location.search)
    if (urlParams.has('schema')) {
      let schema = urlParams.get('schema')
      axios.post('/api/github-proxy/', {
        path: schema.replace('https://raw.githubusercontent.com/AMRC-FactoryPlus/schemas/main/', ''),
      }).then(k => {
        this.schema = k.data.data
        if (!this.schema) {
          this.schemaBrowserVisible = true
        }
      }).catch(error => {
        // Clear the query string if the schema is invalid
        urlParams.delete('schema')
      })
    } else {
      this.schemaBrowserVisible = true
    }
  },

  methods: {
    create (type) {
      switch (type) {
        case 'metric':
          console.log('Creating metric')

          this.$set(this.schema.properties, 'New Metric', {
            allOf: [
              {
                $ref: 'https://raw.githubusercontent.com/AMRC-FactoryPlus/schemas/main/Common/Metric-v1.json',
              },
              {
                properties: {
                  Documentation: {
                    default: '',
                  },
                  Sparkplug_Type: {
                    enum: [
                      'String',
                    ],
                  },
                },
              },
            ],
          })
      }
    },

    handleMetricSelection (e) {
      this.selectedMetric = e
    },

    updateName (name) {
      // This doesn't work for more than one update. Save button next to the input?

      console.log('Updating name to: ', name)
      let oldName = this.selectedMetric.name;
      this.$set(this.schema.properties, name, this.schema.properties[this.selectedMetric.name])
      delete this.schema.properties[oldName];
    },

    updateMetric (updatedMetric) {
      this.updateNestedMetric(this.schema.properties, updatedMetric)
    },

    // ! Needs fixing - UUIDS everywhere and go and find? We can then strip before saving
    updateNestedMetric (rows, updatedMetric) {
      for (let i = 0; i < rows.length; i++) {
        if (rows[i].id === updatedMetric.id) {
          this.$set(rows, i, updatedMetric)
          return true
        } else if (rows[i].children && rows[i].children.length) {
          let found = this.updateNestedMetric(rows[i].children, updatedMetric)
          if (found) {
            return true
          }
        }
      }
      return false
    },

    download () {
      download(JSON.stringify(this.schema, null, 2),
          `${this.schema.$id.replace('https://raw.githubusercontent.com/AMRC-FactoryPlus/schemas/main/', '')}`,
          'text/plain')
    },

    copy () {
      navigator.clipboard.writeText(JSON.stringify(this.schema, null, 2))
      window.showNotification({
        title: 'Copied',
        description: 'The JSON for this schema has been copied to the clipboard.',
        type: 'success',
      })
    },

    open () {
      this.schemaBrowserVisible = true
    },

    selectSchema (schema) {
      this.schemaBrowserVisible = false
      this.schema = schema.rawSchema

      // Remove the URL up to main/ and .json from the end of the schema $id
      if (this.schema.$id.replace('https://raw.githubusercontent.com/AMRC-FactoryPlus/schemas/main/', '') ===
          undefined) {
        this.name = this.schema.$id.replace('https://raw.githubusercontent.com/AMRC-FactoryPlus/schemas/main/', '').
            replace('.json', '')
      }
    },

    maybeNew () {
      window.showNotification({
        title: 'Are you sure?',
        description: 'This will clear the current editor and any changes that you have not copied or downloaded will be lost.',
        type: 'error',
        persistent: true,
        buttons: [
          {
            text: 'New Schema', type: 'error', loadingOnClick: true, action: () => {
              window.hideNotification({ id: 'e9e52913-a393-4031-b37c-4704813729e4' })
              this.selectSchema({
                parsedSchema: null,
                schemaObj: null,
                rawSchema: {
                  '$id': `https://raw.githubusercontent.com/AMRC-FactoryPlus/schemas/main/${undefined}`,
                  '$schema': 'https://json-schema.org/draft/2020-12/schema',
                  'title': undefined,
                  'description': undefined,
                  'type': 'object',
                  'properties': {
                    'Schema_UUID': { 'const': uuidv4() },
                    'Instance_UUID': {
                      'description': 'The unique identifier for this object. (A UUID specified by RFC4122).',
                      'type': 'string',
                      'format': 'uuid',
                    },
                  },
                  'required': ['Schema_UUID', 'Instance_UUID'],
                },
              })
            },
          },
          { text: 'Cancel', isClose: true },
        ],
        id: 'e9e52913-a393-4031-b37c-4704813729e4',
      })
    },
  },

  data () {
    return {

      isContainer: true,
      // deviceSchemas
      deviceSchemas: null,
      deviceSchemasLoading: null,
      deviceSchemasLoaded: null,
      deviceSchemasQueryBank: null,
      deviceSchemasRouteVar: null,
      deviceSchemasForceLoad: true,

      // deviceSchemaVersions
      deviceSchemaVersions: null,
      deviceSchemaVersionsLoading: null,
      deviceSchemaVersionsLoaded: null,
      deviceSchemaVersionsQueryBank: null,

      schemaBrowserVisible: false,
      schema: null,
      name: 'New_Schema-v1',
      selectedMetric: null,

    }
  },

  validations () {
    return {}
  },
}
</script>
