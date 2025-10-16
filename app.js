const { createApp } = Vue;

createApp({
  data() {
    return {
      // Konfiguration
      apiUrl: "http://localhost:8000/api.php",
      date: new Date().toISOString().slice(0, 10),
      stations: [],
      groups: {},
      loading: false,
      error: "",

      // Data
      holidays: [],
      vacations: [],
      newHoliday: { date: "", description: "" },
      newVacation: { start_date: "", end_date: "", station_id: "" },
    };
  },
  computed: {
    // Returnerer alle helligdage
    currentHolidays() {
      const today = new Date().toISOString().slice(0, 10);
      return this.holidays.filter((h) => h.date >= today);
    },
    // Returnerer alle ferieperioder
    currentVacations() {
      const today = new Date().toISOString().slice(0, 10);
      return this.vacations.filter(
        (v) => v.start_date <= today && v.end_date >= today
      );
    },
  },
  mounted() {
    // Hent data når appen startes
    this.loadStations();
    this.loadData();
  },
  methods: {
    // VAGTPLAN
    async loadStations() {
      try {
        // Hent alle stationer
        const res = await fetch(`${this.apiUrl}?endpoint=getStations`);
        const data = await res.json();
        if (!Array.isArray(data))
          throw new Error("Stations fetch returnerede ikke et array");

        this.stations = data;

        // Opdater aktive grupper ud fra den valgte dato
        this.refresh();
      } catch (e) {
        this.error =
          "Kunne ikke hente stations. Start php-serveren (php -S localhost:8000) eller tjek API: " +
          e;
      }
    },

    async refresh() {
      this.loading = true;
      this.groups = {};
      this.error = "";

      try {
        // Hent aktiv gruppe for hver station
        const promises = this.stations.map((s) =>
          fetch(
            `${this.apiUrl}?endpoint=group&stationId=${s.id}&date=${this.date}`
          )
            .then((r) =>
              r.ok
                ? r.json()
                : r.text().then((t) => {
                    throw t;
                  })
            )
            .then((data) => ({ id: s.id, data }))
        );

        const results = await Promise.all(promises);

        // Gem resultatet for hver station
        results.forEach((r) => (this.groups[r.id] = r.data));
      } catch (err) {
        this.error = "Fejl ved hent af grupper: " + err;
      } finally {
        this.loading = false;
      }
    },

    // ADMIN
    async loadData() {
      // Hent helligdage og ferieperioder
      try {
        const [hol, vac] = await Promise.all([
          axios.get(`${this.apiUrl}?endpoint=getHolidays`),
          axios.get(`${this.apiUrl}?endpoint=getVacations`),
        ]);
        this.holidays = hol.data;
        this.vacations = vac.data;
      } catch (err) {
        console.error("Fejl ved hent af data:", err);
      }
    },

    // Formatér dato
    formatDate(dateStr) {
      if (!dateStr) return "";
      const d = new Date(dateStr);
      const day = String(d.getDate()).padStart(2, "0");
      const month = String(d.getMonth() + 1).padStart(2, "0");
      const year = d.getFullYear();
      return `${day}.${month}.${year}`;
    },

    // Tilføj ny helligdag
    async addHoliday() {
      if (!this.newHoliday.date || !this.newHoliday.description) return;
      try {
        await axios.post(`${this.apiUrl}?endpoint=addHoliday`, this.newHoliday);
        this.newHoliday = { date: "", description: "" };
        this.loadData();
      } catch (err) {
        console.error("Fejl ved tilføjelse af helligdag:", err);
      }
    },

    // Slet helligdag
    async deleteHoliday(id) {
      if (!confirm("Er du sikker på, at du vil slette denne helligdag?"))
        return;
      try {
        await axios.delete(`${this.apiUrl}?endpoint=deleteHoliday`, {
          data: { id },
        });
        this.loadData();
      } catch (err) {
        console.error("Fejl ved sletning af helligdag:", err);
      }
    },

    // Tilføj ny ferieperiode
    async addVacation() {
      if (
        !this.newVacation.start_date ||
        !this.newVacation.end_date ||
        !this.newVacation.station_id
      )
        return;
      const payload = { ...this.newVacation };
      try {
        await axios.post(`${this.apiUrl}?endpoint=addVacation`, payload);
        this.newVacation = { start_date: "", end_date: "", station_id: "" };
        this.loadData();
      } catch (err) {
        console.error("Fejl ved tilføjelse af ferie:", err);
      }
    },

    // Slet ferieperiode
    async deleteVacation(id) {
      if (!confirm("Er du sikker på, at du vil slette denne ferieperiode?"))
        return;
      try {
        await axios.delete(`${this.apiUrl}?endpoint=deleteVacation`, {
          data: { id },
        });
        this.loadData();
      } catch (err) {
        console.error("Fejl ved sletning af ferie:", err);
      }
    },
  },
}).mount("#app");
