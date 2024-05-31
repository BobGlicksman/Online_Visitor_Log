package clientinfo

import (
	"bufio"
	"database/sql"
	"encoding/json"
	"fmt"
	"time"

	// "encoding/csv"e
	"flag"
	"os"
	"path/filepath"

	// "strings"

	_ "github.com/go-sql-driver/mysql"
	_ "github.com/gregmakernexus/sheets-utilities"
)

var clear = flag.Bool("clear", false, "Delete and reenter DB and sheet info")

var log *debug.DebugClient
var data [][]string

type visitor_config struct {
	DBName           string `json:"dbName"`
	URL              string `json:"url"`
	Userid           string `json:"id"`
	Pass             string `json:"pass"`
	SpreadSheetTitle string `json:"SpreadSheet"`
	SheetName        string `json:"Sheet"`
}

func newClientInfo(log *debug.DebugClient) (*[][]string, error) {
	// Read config. if not there create it
	c := new(visitor_config)
	if err := c.dirSetup(); err != nil {
		return nil, err
	}
	_, err := os.Stat(".visitorConfig.json")
	if err != nil {
		log.V(0).Printf("Creating Configuration File")
		if err = c.build(".visitorConfig.json"); err != nil {
			log.V(0).Fatalf("Error creating config file. Error:%v", err)
		}
	}
	byteJson, err := os.ReadFile(".visitorConfig.json")
	if err != nil {
		log.V(0).Fatalf("Error reading config file")
	}
	err = json.Unmarshal(byteJson, c)
	/*--------------------------------------------------------------
	 * Open the database.  Parameters were collected in the config
	 *--------------------------------------------------------------*/
	// @tcp(localhost:5555)/dbname?tls=skip-verify&autocommit=true
	dataSource := fmt.Sprintf("%v:%v@tcp(%v)/%v?tls=skip-verify",
		c.Userid, c.Pass, c.URL, c.DBName)
	log.V(0).Printf("db:%v\n", dataSource)
	db, err := sql.Open("mysql", dataSource)
	if err != nil {
		log.V(0).Fatal(err)
	}
	/*---------------------------------------------------------------
	 * Get list of tables in the database.  (not necessary for this app)
	 *--------------------------------------------------------------*/
	log.V(0).Printf("db open complete%v\n", db)
	r, err := db.Query("SHOW TABLES")
	if err != nil {
		log.V(0).Fatal(err)
	}
	var table string
	for r.Next() {
		r.Scan(&table)
		fmt.Println(table)
	}

	/*------------------------------------------------------
	 *  Read the ovl_list table.
	 *-----------------------------------------------------*/
	r, err = db.Query("SELECT * FROM clientInfo")
	if err != nil {
		log.V(0).Fatal(err)
	}
	cols, err := r.Columns()
	if err != nil {
		return nil, fmt.Errorf("error importing database columns: %v", err)
	}
	/*----------------------------------------------------------
	 * Convert the object returned from db to a 2d slice.
	 *---------------------------------------------------------*/
	data = make([][]string, 0)
	data = append(data, cols)
	fmt.Println(cols)
	// Result is your slice string.
	rawResult := make([][]byte, len(cols))
	dest := make([]interface{}, len(cols)) // A temporary interface{} slice
	for i := range rawResult {
		dest[i] = &rawResult[i] // Put pointers to each string in the interface slice
	}

	for r.Next() {
		err = r.Scan(dest...)
		if err != nil {
			return nil, fmt.Errorf("error importing database record:%v", err)
		}
		result := make([]string, len(cols))
		for i, raw := range rawResult {
			if raw == nil {
				result[i] = "\\N"
			} else {
				result[i] = string(raw)
			}
		}
		data = append(data, result)
	}

	return &data, err
}

// clearConfig creates directories and deletes config file if it exists
func clearConfig() error {
	// Read config. if not there create it
	c := new(visitor_config)
	if err := c.dirSetup(); err != nil {
		return err
	}
	return os.Remove(".visitorConfig.json")

}

// Cli read with prompt
func input_config(rd *bufio.Scanner, prompt string) string {
	done := false
	response := ""
	for x := 0; x < 10; x++ {
		if done {
			return response
		}
		fmt.Printf("%v", prompt)
		rd.Scan()
		response = rd.Text()
		if response != "" {
			done = true
		}
		time.Sleep(time.Second + 10)
	}
	return response
}

// Create directories
func (c *visitor_config) dirSetup() error {
	home, err := os.UserHomeDir()
	if err != nil {
		log.Fatal(err)
	}
	configPath := filepath.Join(home, ".makerNexus")
	if err := os.Chdir(configPath); err != nil {
		return fmt.Errorf("error changing to home directory")
	}
	/*----------------------------------------------------------------
	 * if directory does not exist then create it
	 *----------------------------------------------------------------*/
	if _, err := os.Stat(configPath); os.IsNotExist(err) {
		if err := os.Mkdir(".makerNexus", 0777); err != nil {
			return fmt.Errorf("error creating directory .makernexus")
		}
	}
	return nil
}

// Prompt user for config information and write it to disk in a hidden
// directory.
func (c *visitor_config) build(filename string) error {
	fmt.Println("Generating configuration file.  All fields are required. Hit ctrl-c to exit.")
	home, err := os.UserHomeDir()
	if err != nil {
		log.Fatal(err)
	}
	configPath := filepath.Join(home, ".makerNexus")
	if err := os.Chdir(configPath); err != nil {
		return fmt.Errorf("error changing to home directory")
	}
	rd := bufio.NewScanner(os.Stdin)
	c.URL = input_config(rd, "Enter database URL (including port #):")
	c.DBName = input_config(rd, "Enter database name:")
	c.Userid = input_config(rd, "Enter database remote user: ")
	c.Pass = input_config(rd, "Enter database password: ")
	c.SpreadSheetTitle = input_config(rd, "Enter spreadsheet title: ")
	c.SheetName = input_config(rd, "Enter sheet name: ")
	buf, err := json.Marshal(c)
	if err != nil {
		return err
	}
	if err := os.WriteFile(filename, buf, 0777); err != nil {
		return err
	}
	return nil
}
