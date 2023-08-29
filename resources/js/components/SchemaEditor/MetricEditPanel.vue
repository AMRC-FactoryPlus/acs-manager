<!--
  -  Factory+ / AMRC Connectivity Stack (ACS) Manager component
  -  Copyright 2023 AMRC
  -->

<template>
  <div v-if="this.selectedMetric">
    <Wrapper v-if="metricSchema">
      <template #description>
        The name of the metric
      </template>
      <template #content>
        <div class="p-4 w-full">
          <Input :showDescription="false"
                 :control="{name: 'Name',}"
                 :valid="{}"
                 :value="localName"
                 @input="updateName"
          ></Input>
        </div>
      </template>
    </Wrapper>
    <Wrapper v-if="metricSchema && types">
      <template #description>
        {{metricSchema.properties.Sparkplug_Type.description}}
      </template>
      <template #content>
        <Dropdown
            multi
            class="p-4"
            :value="localMetric.Sparkplug_Type.enum"
            @input="updateType"
            :valid="{}"
            :control="{
              name: metricSchema.properties.Sparkplug_Type.title,
              options: availableTypes
            }"></Dropdown>
      </template>
    </Wrapper>
    <Wrapper v-if="metricSchema">
      <template #description>{{metricSchema.properties.Eng_Unit.description}}</template>
      <template #content>
        <div class="p-4 w-full">
          <Input :showDescription="false"
                 :control="{name: 'Engineering Unit',}"
                 :valid="{}"
                 v-model="localMetric.Eng_Unit.default"
          ></Input>
        </div>
      </template>
    </Wrapper>
    <Wrapper v-if="metricSchema">
      <template #description>{{metricSchema.properties.Documentation.description}}</template>
      <template #content>
        <div class="p-4 w-full">
          <Input :showDescription="false"
                 :control="{name: 'Description',}"
                 :valid="{}"
                 v-model="localMetric.Documentation.default"
          ></Input>
        </div>
      </template>
    </Wrapper>
  </div>
</template>

<script>
export default {

  name: 'MetricEditPanel',

  components: {
    'Dropdown': () => import(/* webpackPrefetch: true */ '../FormControls/Dropdown.vue'),
  },

  props: {
    /**
     * The details of the metric
     */
    selectedMetric: {
      type: Object,
      default: () => {
        return {}
      },
    },
  },

  mounted () {
    axios.post('/api/github-proxy/', {
      path: 'Common/Metric-v1.json',
    }).then(k => {
      let data = k.data
      this.metricSchema = data.data
    }).catch(error => {
      if (error && error.response && error.response.status === 401) {
        this.goto_url('/login')
      }
      this.handleError(error)
    })
    axios.post('/api/github-proxy/', {
      path: 'Common/Types/Sparkplug_Types-v1.json',
    }).then(k => {
      let data = k.data
      this.types = data.data.enum
    }).catch(error => {
      if (error && error.response && error.response.status === 401) {
        this.goto_url('/login')
      }
      this.handleError(error)
    })
  },

  watch: {
    selectedMetric: {
      handler (val) {
        this.localName = val.name
        this.localMetric = {
          ...{
            Documentation: {
              default: '',
            },
            Sparkplug_Type: {
              default: ['String'],
            },
            Eng_Unit: {
              default: '',
            },
            Eng_Low: {
              default: '',
            },
            Eng_High: {
              default: '',
            },
          },
          ...val.property.allOf[1].properties,
        }
      },
      deep: true,
    },
  },

  computed: {
    availableTypes () {
      return this.types.map(e => {
        return {
          title: e,
          value: e,
        }
      })
    },
  },

  methods: {
    isValidType (val, options) {
      return options.some(e => e.value === val)
    },

    updateType (type) {
      this.localMetric.Sparkplug_Type.enum = type
    },
    updateName (e) {
      this.localName = e
      this.$emit('updateName', this.localName)
    },
    updateMetric () {
      this.$emit('updateMetric', {
        allOf: [
          {
            $ref: 'https://raw.githubusercontent.com/AMRC-FactoryPlus/schemas/main/Common/Metric-v1.json',
          },
          {
            properties: this.localMetric,
          },
        ],
      })
    },
  },

  data () {
    return {
      localName: this.selectedMetric?.name,
      localMetric: this.selectedMetric?.property,
      metricSchema: null,
      types: null,
    }
  },
}
</script>

